<?php

namespace App\Services;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class PortalHealthService
{
    protected string $loginUrl;
    protected int $timeout = 5;
    protected float $lastResponseTime = 0.0;

    public function __construct()
    {
        $this->loginUrl = rtrim((string) config('services.portal.url'), '/') . '/login';
    }

    public function getLastResponseTime(): float
    {
        return $this->lastResponseTime;
    }

    public function isAlive(bool $bypassCache = false): bool
    {
        // If the circuit breaker is already tripped, skip the probe entirely unless bypassed.
        if (!$bypassCache && Cache::get('sre_circuit_breaker_portal_down') === true) {
            $this->lastResponseTime = (float) $this->timeout;
            return false;
        }

        $client = new Client([
            'timeout'         => $this->timeout,
            'connect_timeout' => $this->timeout,
            'allow_redirects' => true,
        ]);

        $startTime = microtime(true);
        try {
            $response = $client->get($this->loginUrl);
            $this->lastResponseTime = microtime(true) - $startTime;

            if ($response->getStatusCode() !== 200) {
                if (!$bypassCache) {
                    $this->tripCircuitBreaker(
                        'Non-200 status received: ' . $response->getStatusCode()
                    );
                }
                return false;
            }

            $crawler = new Crawler((string) $response->getBody());

            // Verify that the login form's authentication controls are still present.
            $hasUsernameField = $crawler->filter('input[name="username"]')->count() > 0
                || $crawler->filter('input[name="email"]')->count() > 0;
            $hasPasswordField = $crawler->filter('input[type="password"]')->count() > 0;

            if (!$hasUsernameField || !$hasPasswordField) {
                if (!$bypassCache) {
                    $this->tripCircuitBreaker(
                        'Portal DOM structure changed — authentication fields missing from login page.'
                    );
                }
                return false;
            }

            // If we bypassed cache and successfully connected, untrip the circuit breaker!
            if ($bypassCache) {
                Cache::forget('sre_circuit_breaker_portal_down');
            }

            return true;

        } catch (Exception $e) {
            $this->lastResponseTime = microtime(true) - $startTime;
            if (!$bypassCache) {
                $this->tripCircuitBreaker($e->getMessage());
            }
            return false;
        }
    }

    /**
     * Activate the circuit breaker for 10 minutes and emit an alert log entry.
     */
    protected function tripCircuitBreaker(string $reason): void
    {
        Log::channel('sync')->alert('Circuit breaker tripped — portal unreachable or structurally degraded.', [
            'reason'   => $reason,
            'cooldown' => '10 minutes',
        ]);

        Cache::put('sre_circuit_breaker_portal_down', true, now()->addMinutes(10));
    }
}

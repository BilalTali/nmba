<?php

namespace App\Services;

use App\Exceptions\PermanentSyncException;
use App\Exceptions\TransientSyncException;
use App\Models\Event;
use App\Services\Contracts\PortalSyncInterface;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\DomCrawler\Crawler;

class HttpPortalSyncService implements PortalSyncInterface
{
    protected string $baseUrl;
    protected string $loginUrl;
    protected string $submitUrl;
    protected string $dashboardUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        $this->baseUrl      = rtrim((string) config('services.portal.url'), '/');
        $this->loginUrl     = $this->baseUrl . '/login';
        $this->submitUrl    = $this->baseUrl . '/event_create';
        $this->dashboardUrl = $this->baseUrl . '/dashboard';
        $this->username     = (string) config('services.portal.email');
        $this->password     = (string) config('services.portal.password');

        if (empty($this->username) || empty($this->password) || empty($this->baseUrl)) {
            throw new RuntimeException('Portal credentials or URL are missing from configuration.');
        }
    }

    public function sync(Event $event): bool
    {
        if ($event->sync_status === 'synced') {
            return true;
        }

        // Fresh CookieJar per sync execution — prevents session bleed between jobs.
        $cookieJar = new CookieJar();

        $client = new Client([
            'cookies'         => $cookieJar,
            'timeout'         => 30,
            'connect_timeout' => 10,
            'read_timeout'    => 30,
            'allow_redirects' => ['max' => 10, 'strict' => false, 'track_redirects' => false],
            'headers'         => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            ],
        ]);

        try {
            $loginToken      = $this->executeLoginHandshake($client);
            $this->authenticateSession($client, $loginToken);
            $submissionToken = $this->retrieveSubmissionToken($client, $loginToken);

            return $this->dispatchPayload($client, $event, $submissionToken);
        } finally {
            unset($client, $cookieJar);
        }
    }

    /**
     * GET the login page and extract the initial CSRF token via multi-strategy fallback.
     */
    protected function executeLoginHandshake(Client $client): string
    {
        try {
            $response = $client->get($this->loginUrl);
        } catch (ConnectException $e) {
            throw new TransientSyncException(
                "Socket connection failed during login handshake: {$e->getMessage()}", 0, $e
            );
        }

        $crawler = new Crawler((string) $response->getBody());

        $token = null;

        if ($crawler->filter('input[name="_token"]')->count() > 0) {
            $token = $crawler->filter('input[name="_token"]')->attr('value');
        } elseif ($crawler->filter('meta[name="csrf-token"]')->count() > 0) {
            $token = $crawler->filter('meta[name="csrf-token"]')->attr('content');
        } elseif ($crawler->filter('input[name="csrf_token"]')->count() > 0) {
            $token = $crawler->filter('input[name="csrf_token"]')->attr('value');
        }

        if (empty($token)) {
            Log::channel('sync')->warning('CSRF handshake token extraction returned empty. Portal may not use CSRF. Proceeding without token.');
            $token = '';
        }

        return $token;
    }

    /**
     * POST credentials and verify an authenticated session was established.
     */
    protected function authenticateSession(Client $client, string $csrfToken): void
    {
        try {
            $postResponse = $client->post($this->baseUrl . '/authenticate', [
                'headers' => [
                    'Referer' => $this->loginUrl,
                ],
                'form_params' => [
                    '_token'   => $csrfToken,
                    'email'    => $this->username,
                    'password' => $this->password,
                ],
            ]);

            $dashHtml = strtolower((string) $postResponse->getBody());

            if (
                !str_contains($dashHtml, 'logout') &&
                !str_contains($dashHtml, 'sign out') &&
                !str_contains($dashHtml, 'dashboard')
            ) {
                throw new \App\Exceptions\AuthenticationSyncException(
                    'Invalid Portal Credentials! Please update the settings. Auto-sync is paused.'
                );
            }
        } catch (ConnectException $e) {
            throw new TransientSyncException(
                "Network loss during authentication transmission: {$e->getMessage()}", 0, $e
            );
        }
    }

    /**
     * GET the event submission form page and extract the post-login CSRF token.
     * Falls back to the login token if the page cannot be reached.
     */
    protected function retrieveSubmissionToken(Client $client, string $fallbackToken): string
    {
        try {
            $response    = $client->get($this->submitUrl);
            $crawler     = new Crawler((string) $response->getBody());

            if ($crawler->filter('input[name="_token"]')->count() > 0) {
                return $crawler->filter('input[name="_token"]')->attr('value');
            }
            if ($crawler->filter('meta[name="csrf-token"]')->count() > 0) {
                return $crawler->filter('meta[name="csrf-token"]')->attr('content');
            }
        } catch (Exception $e) {
            Log::channel('sync')->warning('Post-login CSRF refresh failed; using fallback token.', [
                'error' => $e->getMessage(),
            ]);
        }

        return $fallbackToken;
    }

    /**
     * Build the multipart payload and POST it to the portal.
     * File handles are opened inside a try-finally to guarantee fclose() on all branches.
     */
    protected function dispatchPayload(Client $client, Event $event, string $submissionToken): bool
    {
        $streams = [];

        try {
            $multipart = [
                ['name' => '_token',                             'contents' => $submissionToken],
                ['name' => 'event_name',                        'contents' => $event->event_name],
                ['name' => 'event_date',                        'contents' => $event->event_date->format('d-m-Y')],
                ['name' => 'event_venue',                       'contents' => $event->event_venue],
                ['name' => 'district',                          'contents' => 'Budgam'],
                ['name' => 'block',                             'contents' => (string) $event->block_id],
                ['name' => 'ward',                              'contents' => (string) ($event->ward ?? '')],
                ['name' => 'village',                           'contents' => (string) ($event->village ?? '')],
                ['name' => 'event_category_remark',             'contents' => (string) ($event->event_category_remark ?? '')],
                ['name' => 'attendance_range',                  'contents' => $event->attendance_range],
                ['name' => 'actual_attendance',                 'contents' => (string) $event->actual_attendance],
                ['name' => 'event_coordinator_name',            'contents' => $event->event_coordinator_name],
                ['name' => 'event_coordinator_contact_number',  'contents' => $event->event_coordinator_contact_number],
                ['name' => 'event_coordinator_desig',           'contents' => $event->event_coordinator_desig],
            ];

            foreach ($event->event_category as $cat) {
                $multipart[] = ['name' => 'event_category[]', 'contents' => $cat];
            }
            foreach ($event->target_audience as $aud) {
                $multipart[] = ['name' => 'target_audience[]', 'contents' => $aud];
            }
            foreach ($event->age_group as $age) {
                $multipart[] = ['name' => 'age_group[]', 'contents' => $age];
            }

            foreach ($event->photo_paths as $path) {
                $fullPath = Storage::disk('public')->path($path);

                if (!file_exists($fullPath)) {
                    throw new PermanentSyncException(
                        "Required photo asset missing from storage: {$path}"
                    );
                }

                $handle = fopen($fullPath, 'r');
                if ($handle === false) {
                    throw new PermanentSyncException(
                        "Cannot open file handle for asset: {$fullPath}"
                    );
                }

                $streams[]   = $handle;
                $multipart[] = [
                    'name'     => 'event_photos[]',
                    'contents' => $handle,
                    'filename' => basename($fullPath),
                ];
            }

            // Jitter delay proportional to attempt count to spread out retry storms.
            $jitterBase = min(5, $event->sync_attempts + 1);
            usleep(random_int(500_000 * $jitterBase, 1_500_000 * $jitterBase));

            try {
                return Cache::lock('portal-sync-transmission-lock', 5)->block(
                    3,
                    function () use ($client, $multipart) {
                        $response = $client->post($this->submitUrl, ['multipart' => $multipart]);
                        return $this->evaluateResponse(
                            $response->getStatusCode(),
                            (string) $response->getBody()
                        );
                    }
                );
            } catch (ConnectException $e) {
                throw new TransientSyncException(
                    "Connection lost during multipart upload: {$e->getMessage()}", 0, $e
                );
            } catch (RequestException $e) {
                $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
                if ($status >= 500) {
                    throw new TransientSyncException(
                        "Downstream server error HTTP {$status}.", 0, $e
                    );
                }
                throw new PermanentSyncException(
                    "Portal rejected submission with HTTP {$status}.", 0, $e
                );
            }

        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
        }
    }

    /**
     * Analyse the portal response to determine success, session expiry, or validation failure.
     */
    protected function evaluateResponse(int $statusCode, string $body): bool
    {
        $lower   = strtolower($body);
        $crawler = new Crawler($body);

        // Detect mid-flight session expiry — portal redirected back to login page.
        if (
            str_contains($body, 'name="password"') ||
            str_contains($lower, 'type="password"') ||
            str_contains($lower, 'login-box') ||
            str_contains($lower, 'sign in')
        ) {
            throw new TransientSyncException(
                'Session expired mid-request. Portal redirected to login page. Re-authentication required.'
            );
        }

        // Detect silent validation rejection banners embedded in 200 responses.
        $errorNodes = $crawler->filter('.alert-danger, .error-message, .validation-errors, #error-container');
        if ($errorNodes->count() > 0) {
            throw new PermanentSyncException(
                'Portal returned a validation error banner: ' . trim($errorNodes->first()->text())
            );
        }

        // Detect downstream server exceptions leaked into the response body.
        if (str_contains($lower, 'sql error') || str_contains($lower, 'exception triggered')) {
            throw new TransientSyncException(
                'Downstream server exception detected in response body. Backing off.'
            );
        }

        // HTTP 302 redirect is typically a successful form submission.
        if ($statusCode === 302) {
            if (str_contains($lower, 'error') || str_contains($lower, 'invalid')) {
                throw new PermanentSyncException(
                    'Portal issued a 302 redirect containing error indicators.'
                );
            }
            return true;
        }

        // HTTP 200: scan for known success keywords.
        if ($statusCode === 200) {
            $successKeywords = ['success', 'saved successfully', 'record added', 'created successfully', 'activity logged'];
            foreach ($successKeywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return true;
                }
            }
        }

        return false;
    }
}

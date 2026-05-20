<?php

require __DIR__ . '/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Symfony\Component\DomCrawler\Crawler;

$email = 'dc@budgam';
$password = 'dcbudgamjk@123456#';
$cookieFile = __DIR__ . '/portal_cookies.json';

// Helper function to check session
function checkSession(Client $client) {
    try {
        $start = microtime(true);
        $response = $client->get('https://nashamuktjk.org/enterprise/event_create');
        $duration = microtime(true) - $start;
        
        $body = (string) $response->getBody();
        $crawler = new Crawler($body);
        
        $isCreatePage = $crawler->filter('input[name="event_name"]')->count() > 0;
        
        echo "checkSession: response status = " . $response->getStatusCode() . "\n";
        echo "checkSession: html length = " . strlen($body) . "\n";
        echo "checkSession: contains 'event_name' input: " . ($isCreatePage ? 'YES' : 'NO') . "\n";
        echo "checkSession: first 100 chars of body: " . trim(substr(strip_tags($body), 0, 100)) . "\n";
        
        if ($isCreatePage) {
            $token = '';
            if ($crawler->filter('input[name="_token"]')->count() > 0) {
                $token = $crawler->filter('input[name="_token"]')->attr('value');
            }
            return [
                'valid' => true,
                'token' => $token,
                'duration' => $duration
            ];
        }
    } catch (\Exception $e) {
        echo "checkSession Error: " . $e->getMessage() . "\n";
    }
    return ['valid' => false, 'token' => null];
}

// 1. Try to load cached cookies
$cookieJar = new CookieJar();
if (file_exists($cookieFile)) {
    echo "Found cached cookies file. Loading...\n";
    $cookiesData = json_decode(file_get_contents($cookieFile), true);
    foreach ($cookiesData as $cookieArray) {
        $cookieJar->setCookie(new SetCookie($cookieArray));
    }
} else {
    echo "No cached cookies found.\n";
}

$client = new Client([
    'cookies' => $cookieJar,
    'timeout' => 30,
    'connect_timeout' => 15,
    'headers' => [
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]
]);

echo "Testing session validity...\n";
$sessionStatus = checkSession($client);

if ($sessionStatus['valid']) {
    echo "SUCCESS: Session is VALID! Token: " . $sessionStatus['token'] . " (Checked in " . round($sessionStatus['duration'], 2) . "s)\n";
    echo "No login handshake needed!\n";
} else {
    echo "Session is INVALID or EXPIRED. Performing login handshake...\n";
    
    // Fresh run
    $cookieJar = new CookieJar();
    $client = new Client([
        'cookies' => $cookieJar,
        'timeout' => 30,
        'connect_timeout' => 15,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]
    ]);
    
    echo "Fetching login page...\n";
    $response = $client->get('https://nashamuktjk.org/enterprise/login');
    $body = (string) $response->getBody();
    $crawler = new Crawler($body);
    
    $token = '';
    if ($crawler->filter('input[name="_token"]')->count() > 0) {
        $token = $crawler->filter('input[name="_token"]')->attr('value');
    }
    
    echo "Logging in...\n";
    $postResponse = $client->post('https://nashamuktjk.org/enterprise/authenticate', [
        'headers' => [
            'Referer' => 'https://nashamuktjk.org/enterprise/login',
        ],
        'form_params' => [
            '_token'   => $token,
            'email'    => $email,
            'password' => $password,
        ],
        'allow_redirects' => true
    ]);
    
    // Check session again
    echo "Verifying session after login...\n";
    $sessionStatus = checkSession($client);
    if ($sessionStatus['valid']) {
        echo "Login SUCCESS! Saving cookies to cache...\n";
        file_put_contents($cookieFile, json_encode($cookieJar->toArray()));
    } else {
        echo "Login FAILED!\n";
    }
}

<?php

/**
 * NMBA Agent Portal — Secure Web Cron Trigger
 *
 * This endpoint is called by the Hostinger hPanel Cron Job manager
 * every 5 minutes via an HTTP GET request. It processes up to 10
 * pending queue jobs per invocation using the database driver.
 *
 * TOKEN SECURITY:
 *   The CRON_TOKEN value is loaded exclusively from the Laravel .env
 *   file — it is never stored in source code. To set up:
 *     1. Generate a new token: openssl rand -hex 32
 *     2. Add CRON_TOKEN=<generated_value> to your .env file
 *     3. Update the hPanel cron URL to use the new token value
 *   See README.md > "Deployment Secrets" for full instructions.
 *
 * hPanel Command (template — replace TOKEN with actual value from .env):
 *   curl -s "https://nmbabudgam.in/nmba-cron.php?token=TOKEN" > /dev/null 2>&1
 *
 * THROUGHPUT:
 *   --max-jobs=10 processes up to 10 jobs per 5-minute cycle (~120/hr burst).
 *   A second hPanel cron entry offset by 2 minutes doubles burst capacity to ~240/hr.
 *   See DEPLOYMENT.md for full throughput math and second-cron setup instructions.
 *
 * Server Layout:
 *   public_html/       <-- this file lives here (__DIR__)
 *   nmbaagent/         <-- Laravel app is SIBLING of public_html, NOT child
 */

// ── Load CRON_TOKEN securely from the Laravel .env file ─────────
define('APP_ROOT', dirname(__DIR__) . '/nmbaagent');
define('PHP_BIN',  '/usr/bin/php');
define('LOG_FILE', APP_ROOT . '/storage/logs/cron-worker.log');

/**
 * Minimal, safe .env key=value parser.
 * Reads only what it needs — does NOT eval or include the file.
 */
function loadEnvValue(string $filePath, string $key): string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return '';
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and lines that don't start with KEY=
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }
        [$lineKey, $lineValue] = explode('=', $line, 2);
        if (trim($lineKey) === $key) {
            // Strip surrounding quotes if present
            return trim(trim($lineValue), '"\'');
        }
    }
    return '';
}

$envFile   = APP_ROOT . '/.env';
$cronToken = loadEnvValue($envFile, 'CRON_TOKEN');

// ── Fail secure: reject if token is not configured in .env ───────
if (empty($cronToken)) {
    http_response_code(500);
    die('[' . date('Y-m-d H:i:s') . '] ERROR: CRON_TOKEN is not set in .env. Cron execution blocked.');
}

// ── Security: reject requests without the correct token ─────────
$requestToken = $_GET['token'] ?? '';
if (!hash_equals($cronToken, $requestToken)) {
    http_response_code(403);
    die('Forbidden');
}

// ── Sanity check: ensure artisan actually exists at APP_ROOT ─────
if (!file_exists(APP_ROOT . '/artisan')) {
    http_response_code(500);
    die('ERROR: artisan not found at: ' . APP_ROOT);
}

// ── Guard: prevent overlapping cron runs using a lockfile ────────
$lockFile = sys_get_temp_dir() . '/nmba_queue_worker.lock';
if (file_exists($lockFile)) {
    $lockAge = time() - filemtime($lockFile);
    if ($lockAge < 300) { // Lock expires after 5 minutes
        http_response_code(200);
        die('[' . date('Y-m-d H:i:s') . '] Worker already running (lock age: ' . $lockAge . 's)');
    }
}
touch($lockFile);

// ── Run queue:work with --max-jobs=10 for burst throughput ───────
// --max-jobs=10 processes up to 10 jobs then exits cleanly.
// This prevents PHP-FPM memory leaks from long-lived worker processes
// while still handling burst loads (see DEPLOYMENT.md for throughput math).
$command = PHP_BIN . ' ' . APP_ROOT . '/artisan queue:work database --max-jobs=10 --tries=10 --timeout=110 >> ' . LOG_FILE . ' 2>&1';
$output  = shell_exec($command);

// Release the lockfile after execution completes
@unlink($lockFile);

// ── Respond with timestamp confirmation ──────────────────────────
http_response_code(200);
header('Content-Type: text/plain');
echo '[' . date('Y-m-d H:i:s') . '] Queue worker cycle completed (max-jobs=10).' . PHP_EOL;
if ($output) {
    echo $output;
}

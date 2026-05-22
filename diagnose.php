<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

echo "\n=== 1. EVENT STATUS BREAKDOWN ===\n";
$rows = DB::table('events')->select('sync_status', DB::raw('count(*) as total'))->groupBy('sync_status')->get();
foreach ($rows as $r) {
    echo str_pad($r->sync_status, 25) . ': ' . $r->total . "\n";
}

echo "\n=== 2. JOBS TABLE ===\n";
$total = DB::table('jobs')->count();
$reserved = DB::table('jobs')->whereNotNull('reserved_at')->count();
$available = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '<=', time())->count();
$delayed = DB::table('jobs')->whereNull('reserved_at')->where('available_at', '>', time())->count();
echo "Total jobs in queue:  $total\n";
echo "Reserved (running):   $reserved\n";
echo "Available (ready):    $available\n";
echo "Delayed (future):     $delayed\n";

echo "\n=== 3. FAILED JOBS ===\n";
$failed = DB::table('failed_jobs')->count();
echo "Failed jobs table: $failed\n";
if ($failed > 0) {
    $last = DB::table('failed_jobs')->orderBy('failed_at', 'desc')->first();
    echo "Last failure: " . $last->failed_at . "\n";
    echo "Exception: " . substr($last->exception, 0, 300) . "\n";
}

echo "\n=== 4. CACHE / CIRCUIT BREAKER STATE ===\n";
$cb = Cache::get('sre_circuit_breaker_portal_down');
echo "Circuit breaker DOWN: " . ($cb ? 'YES - SYNC BLOCKED' : 'No') . "\n";
$pausedAuto = Cache::get('auto_sync_paused');
echo "Auto sync paused:     " . ($pausedAuto ? 'YES' : 'No') . "\n";
$watchdog = Cache::has('sre_dashboard_cron_watchdog');
echo "Dashboard watchdog active: " . ($watchdog ? 'YES' : 'No') . "\n";

echo "\n=== 5. LAST 5 SUCCESSFULLY SYNCED EVENTS ===\n";
$last5 = DB::table('events')->where('sync_status', 'synced')
    ->orderBy('synced_at', 'desc')->limit(5)->get(['id', 'event_name', 'synced_at']);
foreach ($last5 as $e) {
    echo "#" . $e->id . " | " . $e->synced_at . " | " . substr($e->event_name, 0, 40) . "\n";
}

echo "\n=== 6. LAST 5 FAILED/PENDING WITH ERRORS ===\n";
$errors = DB::table('events')
    ->where('sync_status', 'pending')
    ->whereNotNull('last_error_log')
    ->orderBy('last_attempt_at', 'desc')
    ->limit(5)
    ->get(['id', 'sync_attempts', 'last_attempt_at', 'last_error_log']);
foreach ($errors as $e) {
    echo "#" . $e->id . " | attempts: " . $e->sync_attempts . " | " . $e->last_attempt_at . "\n";
    echo "  Error: " . substr($e->last_error_log, 0, 200) . "\n";
}

echo "\n=== 7. LOCK FILE STATE ===\n";
$lock = '/tmp/nmba_queue_worker.lock';
if (file_exists($lock)) {
    $age = time() - filemtime($lock);
    echo "Lock file EXISTS. Age: {$age}s\n";
    echo "Expires after: 300s\n";
    echo "STALE: " . ($age > 300 ? 'YES - will block next cron!' : 'No, still valid') . "\n";
} else {
    echo "No lock file. Queue is idle and ready.\n";
}

echo "\n=== 8. PENDING EVENTS WITH HIGH ATTEMPTS (STUCK) ===\n";
$stuck = DB::table('events')->where('sync_status', 'pending')->where('sync_attempts', '>=', 5)->count();
$veryStuck = DB::table('events')->where('sync_status', 'pending')->where('sync_attempts', '>=', 10)->count();
echo "Pending with 5+ attempts:  $stuck\n";
echo "Pending with 10+ attempts: $veryStuck\n";

echo "\n=== 9. EVENTS WITH 'syncing' STATUS (STALE?) ===\n";
$syncing = DB::table('events')->where('sync_status', 'syncing')->count();
echo "Events stuck in 'syncing': $syncing\n";
if ($syncing > 0) {
    $sample = DB::table('events')->where('sync_status','syncing')->orderBy('last_attempt_at','desc')->first(['id','last_attempt_at']);
    echo "Last syncing attempt: " . ($sample->last_attempt_at ?? 'N/A') . "\n";
}

echo "\n=== 10. SYNC RATE: Last 1 hour synced ===\n";
$oneHourAgo = now()->subHour()->toDateTimeString();
$recentSynced = DB::table('events')->where('sync_status','synced')->where('synced_at', '>=', $oneHourAgo)->count();
echo "Events synced in last 60 min: $recentSynced\n";

echo "\n=== DONE ===\n";

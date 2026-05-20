<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Event;
use App\Jobs\SyncEventJob;

echo "=== Dispatching all pending events ===" . PHP_EOL;
$events = Event::where('sync_status', 'pending')->get();
foreach ($events as $event) {
    dispatch(new SyncEventJob($event));
}
echo "Dispatched " . $events->count() . " jobs directly!" . PHP_EOL;

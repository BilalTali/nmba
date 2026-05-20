<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$health = app(App\Services\PortalHealthService::class);
$isAlive = $health->isAlive(true);
echo "Portal Alive: " . ($isAlive ? "TRUE" : "FALSE") . PHP_EOL;

if (!$isAlive) {
    echo "Circuit breaker status: " . (\Illuminate\Support\Facades\Cache::get('sre_circuit_breaker_portal_down') ? "TRIPPED" : "CLEAR") . PHP_EOL;
}

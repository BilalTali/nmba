<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Grouped sync statuses
    $counts = \App\Models\Event::selectRaw('sync_status, COUNT(*) as total')
        ->groupBy('sync_status')
        ->pluck('total', 'sync_status');
        
    $totalSynced = (int) $counts->get('synced', 0);
    $totalPending = (int) $counts->get('pending', 0);
    $totalFailed = (int) $counts->get('failed_permanently', 0);
    
    // Synced today
    $syncedToday = \App\Models\Event::where('sync_status', 'synced')
        ->whereDate('updated_at', today())
        ->count();

    $liveMetrics = [
        ['label' => 'Total Events', 'value' => number_format(\App\Models\Event::count())],
        ['label' => 'Total Synced', 'value' => number_format($totalSynced)],
        ['label' => 'Synced Today', 'value' => number_format($syncedToday)],
        ['label' => 'Pending / Syncing / Failed', 'value' => number_format($totalPending + $totalFailed + (int) $counts->get('syncing', 0))],
    ];

    // Chart Data: Status Pie Chart
    $statusData = array_values(array_filter([
        ['name' => 'Synced', 'value' => $totalSynced, 'fill' => '#10b981'],
        ['name' => 'Pending', 'value' => $totalPending, 'fill' => '#f59e0b'],
        ['name' => 'Failed', 'value' => $totalFailed, 'fill' => '#f43f5e'],
        ['name' => 'Syncing', 'value' => (int) $counts->get('syncing', 0), 'fill' => '#3b82f6'],
    ], fn($item) => $item['value'] > 0));

    // Chart Data: Events over last 7 days Area Chart
    $eventsOverTimeRaw = \App\Models\Event::where('created_at', '>=', now()->subDays(7)->startOfDay())
        ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
        ->groupBy('date')
        ->pluck('count', 'date');

    $eventsOverTime = collect();
    for ($i = 7; $i >= 0; $i--) {
        $carbonDate = now()->subDays($i);
        $dateStr = $carbonDate->format('Y-m-d');
        $displayDate = $carbonDate->format('M d');
        
        $eventsOverTime->push([
            'date' => $displayDate,
            'count' => $eventsOverTimeRaw->get($dateStr, 0)
        ]);
    }

    // Chart Data: Block-wise Weekly Status Bar Chart
    $blocks = \App\Models\Block::pluck('name', 'id')->toArray();

    $blockData = \App\Models\Event::where('created_at', '>=', now()->subDays(7)->startOfDay())
        ->selectRaw('block_id, sync_status, DATE(created_at) as created_date, COUNT(*) as count')
        ->groupBy('block_id', 'sync_status', 'created_date')
        ->get();

    $eventsByBlock = [];
    foreach ($blocks as $id => $name) {
        $eventsByBlock[$id] = [
            'name' => $name, 
            'today_synced' => 0, 'today_pending' => 0, 'today_failed' => 0,
            'week_synced' => 0, 'week_pending' => 0, 'week_failed' => 0
        ];
    }
    
    $todayStr = now()->format('Y-m-d');

    foreach ($blockData as $row) {
        $id = $row->block_id;
        if (!isset($eventsByBlock[$id])) continue;
        
        $isToday = ($row->created_date === $todayStr);
        $status = $row->sync_status;
        
        if ($status === 'synced') {
            $eventsByBlock[$id]['week_synced'] += $row->count;
            if ($isToday) $eventsByBlock[$id]['today_synced'] += $row->count;
        } elseif ($status === 'pending' || $status === 'syncing') {
            $eventsByBlock[$id]['week_pending'] += $row->count;
            if ($isToday) $eventsByBlock[$id]['today_pending'] += $row->count;
        } else {
            $eventsByBlock[$id]['week_failed'] += $row->count;
            if ($isToday) $eventsByBlock[$id]['today_failed'] += $row->count;
        }
    }
    
    $eventsByBlock = array_values(array_filter($eventsByBlock, fn($b) => $b['week_synced'] > 0 || $b['week_pending'] > 0 || $b['week_failed'] > 0));
    usort($eventsByBlock, fn($a, $b) => ($b['week_synced'] + $b['week_pending'] + $b['week_failed']) - ($a['week_synced'] + $a['week_pending'] + $a['week_failed']));

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'liveMetrics' => $liveMetrics,
        'statusData' => $statusData,
        'eventsOverTime' => $eventsOverTime,
        'eventsByBlock' => $eventsByBlock
    ]);
});

Route::middleware(['auth', 'district_access'])->group(function () {
    Route::get('/events', [\App\Http\Controllers\EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [\App\Http\Controllers\EventController::class, 'create'])->name('events.create');
    Route::post('/events', [\App\Http\Controllers\EventController::class, 'store'])->name('events.store');
    Route::get('/events/export', [\App\Http\Controllers\EventController::class, 'exportCsv'])->name('events.export');
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\EventController::class, 'dashboard'])->name('dashboard');
    Route::resource('users', \App\Http\Controllers\UserController::class);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Admin routes — rate limited to prevent self-inflicted DoS
    Route::post('/events/{event}/toggle-sync', [\App\Http\Controllers\EventController::class, 'toggleSyncStatus'])
        ->middleware('throttle:15,1')->name('events.toggleSync');
    Route::post('/events/{event}/retry-sync', [\App\Http\Controllers\EventController::class, 'retrySync'])
        ->middleware('throttle:5,1')->name('events.retrySync');
    Route::post('/events/toggle-auto-sync', [\App\Http\Controllers\EventController::class, 'toggleAutoSync'])
        ->middleware('throttle:10,1')->name('events.toggleAutoSync');
    Route::post('/events/force-sync', [\App\Http\Controllers\EventController::class, 'forceSync'])
        ->name('events.force-sync');
    Route::post('/events/purge-synced-media', [\App\Http\Controllers\EventController::class, 'purgeSyncedMedia'])
        ->middleware('throttle:30,1')->name('events.purge-media');
    // Polled every 15s — allow max 10/min per user (2x safety margin)
    Route::get('/events/check-portal', [\App\Http\Controllers\EventController::class, 'checkPortalHealth'])
        ->middleware('throttle:10,1')->name('events.check-portal');

    // Setting env route
    Route::post('/settings/env', [\App\Http\Controllers\SettingsController::class, 'updateEnv'])
        ->middleware('throttle:10,1')->name('settings.env');

    // Diagnostic Logs
    Route::get('/admin/logs/sync', [\App\Http\Controllers\EventController::class, 'viewSyncLogs'])
        ->name('admin.logs.sync');
    Route::get('/admin/logs/audit', [\App\Http\Controllers\EventController::class, 'viewAuditLogs'])
        ->name('admin.logs.audit');
});

// Profile routes moved to admin group

// Block worker routes
Route::middleware(['auth'])->prefix('block')->name('block.')->group(function () {
    Route::get('/events', [\App\Http\Controllers\BlockEventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [\App\Http\Controllers\BlockEventController::class, 'create'])->name('events.create');
    Route::post('/events', [\App\Http\Controllers\BlockEventController::class, 'store'])->name('events.store');
});

require __DIR__.'/auth.php';

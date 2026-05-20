<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $totalEvents = \App\Models\Event::count();
    $uploadedToday = \App\Models\Event::whereDate('created_at', today())->count();
    $uploadedThisWeek = \App\Models\Event::where('created_at', '>=', now()->subDays(7)->startOfDay())->count();
    $blocksActive = \App\Models\Event::distinct('block_id')->count('block_id');

    $liveMetrics = [
        ['label' => 'Total Events Uploaded', 'value' => number_format($totalEvents)],
        ['label' => 'Uploaded Today', 'value' => number_format($uploadedToday)],
        ['label' => 'Uploaded This Week', 'value' => number_format($uploadedThisWeek)],
        ['label' => 'Active Blocks', 'value' => number_format($blocksActive)],
    ];

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

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'liveMetrics' => $liveMetrics,
        'eventsOverTime' => $eventsOverTime,
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
    Route::get('/admin/synced-events', [\App\Http\Controllers\EventController::class, 'syncedEventsIndex'])->name('admin.synced-events');
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
    Route::post('/events/run-queue-worker', [\App\Http\Controllers\EventController::class, 'runQueueWorkerManually'])
        ->name('events.run-queue-worker');
    Route::post('/events/clear-queue', [\App\Http\Controllers\EventController::class, 'clearQueueManually'])
        ->name('events.clear-queue');
    Route::post('/events/reset-failed', [\App\Http\Controllers\EventController::class, 'resetFailedSyncs'])
        ->name('events.reset-failed');
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

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Jobs\SyncEventJob;
use App\Models\Event;
use App\Services\ImageOptimizationService;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class EventController extends Controller
{
    protected ImageOptimizationService $imageService;

    public function __construct(ImageOptimizationService $imageService)
    {
        $this->imageService = $imageService;
    }

    private function getBlocks(): array
    {
        return [
            13 => 'B.K.Pora', 14 => 'Badgam', 15 => 'Beerwah', 16 => 'Chadoora',
            17 => 'Khag', 18 => 'Khan-Sahib', 19 => 'Nagam', 20 => 'Narbal',
            6915 => 'Parnewa', 6916 => 'Sukhnag Hard Panzoo', 6917 => 'Waterhail',
            6918 => 'Pakherpora', 6919 => 'Charisharief', 6920 => 'Surasyar',
            6921 => 'Soibugh', 6922 => 'Rathsun', 6923 => 'S K Pora',
        ];
    }

    public function dashboard(): \Inertia\Response
    {
        $autoSyncPaused = Cache::get('auto_sync_paused', false);

        // Single grouped query instead of 6 separate COUNT(*) calls
        $counts = Event::selectRaw('sync_status, COUNT(*) as total')
            ->groupBy('sync_status')
            ->pluck('total', 'sync_status');

        $metrics = [
            'total'       => $counts->sum(),
            'pending'     => (int) ($counts->get('pending', 0)),
            'syncing'     => (int) ($counts->get('syncing', 0)),
            'synced'      => (int) ($counts->get('synced', 0)),
            'failed_perm' => (int) ($counts->get('failed_permanently', 0)),
            'transient'   => Event::where('sync_attempts', '>', 0)->where('sync_status', 'pending')->count(),
        ];

        // Enqueue any pending events that slipped through — the persistent queue daemon will pick them up.
        if (!$autoSyncPaused) {
            $this->ensurePendingEventsAreQueued();
        }

        $recentEvents   = Event::orderBy('created_at', 'desc')->limit(20)->get();
        $recentFailures = Event::whereNotNull('last_error_log')->orderBy('last_attempt_at', 'desc')->limit(10)->get();

        // Chart Data: Status Pie Chart
        $statusData = array_values(array_filter([
            ['name' => 'Synced', 'value' => (int) $counts->get('synced', 0), 'fill' => '#10b981'],
            ['name' => 'Pending', 'value' => (int) $counts->get('pending', 0), 'fill' => '#f59e0b'],
            ['name' => 'Failed', 'value' => (int) $counts->get('failed_permanently', 0), 'fill' => '#f43f5e'],
            ['name' => 'Syncing', 'value' => (int) $counts->get('syncing', 0), 'fill' => '#3b82f6'],
        ], fn($item) => $item['value'] > 0));

        // Chart Data: Events by Block Bar Chart
        $blocks = $this->getBlocks();
        $eventsByBlock = Event::selectRaw('block_id, COUNT(*) as count')
            ->groupBy('block_id')
            ->get()->map(function($item) use ($blocks) {
                return [
                    'name' => $blocks[$item->block_id] ?? 'Unknown',
                    'count' => $item->count
                ];
            })->sortByDesc('count')->values();

        // Chart Data: Events over last 30 days Area Chart
        $thirtyDaysAgo = now()->subDays(30)->format('Y-m-d');
        $eventsOverTime = Event::where('event_date', '>=', $thirtyDaysAgo)
            ->selectRaw('DATE(event_date) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()->map(function($item) {
                return [
                    'date' => \Carbon\Carbon::parse($item->date)->format('M d'),
                    'count' => $item->count
                ];
            });

        $envFile = base_path('.env');
        $envContent = file_exists($envFile) ? file_get_contents($envFile) : '';
        preg_match('/^PORTAL_URL=(.*)$/m', $envContent, $urlMatch);
        preg_match('/^PORTAL_EMAIL=(.*)$/m', $envContent, $emailMatch);
        preg_match('/^PORTAL_PASSWORD=(.*)$/m', $envContent, $passwordMatch);

        return \Inertia\Inertia::render('Events/Dashboard', [
            'metrics'        => $metrics,
            'recentEvents'   => $recentEvents,
            'recentFailures' => $recentFailures,
            'autoSyncPaused' => $autoSyncPaused,
            'statusData'     => $statusData,
            'eventsByBlock'  => $eventsByBlock,
            'eventsOverTime' => $eventsOverTime,
            'portalConfig'   => [
                'portal_url' => trim($urlMatch[1] ?? config('services.portal.url', '')),
                'admin_id' => trim($emailMatch[1] ?? config('services.portal.email', '')),
                'admin_password' => trim($passwordMatch[1] ?? config('services.portal.password', ''), '"'),
            ]
        ]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('Events/Create', ['blocks' => $this->getBlocks()]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $uniqueHash = Event::generateUniqueHash(
            $validated['event_name'],
            $validated['event_date'],
            $validated['event_venue'],
            (int) $validated['actual_attendance'],
            (int) $validated['block_id']
        );

        if (Event::where('unique_hash', $uniqueHash)->exists()) {
            return redirect()->back()->withInput()
                ->withErrors(['duplicate' => 'An identical event record already exists in the system.']);
        }

        $photoPaths = [];
        try {
            $photoPaths = $this->imageService->optimizeBatch($request->file('photo'));
        } catch (Exception $e) {
            Log::channel('sync')->warning('Image optimization failure.', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['error' => 'Image processing failed: ' . $e->getMessage()]);
        }

        DB::beginTransaction();
        try {
            $event = Event::create(array_merge($validated, [
                'photo_paths' => $photoPaths,
                'unique_hash' => $uniqueHash,
                'sync_status' => 'pending',
            ]));

            DB::commit();

            Log::channel('sync')->info('Event created and queued.', [
                'event_id' => $event->id, 'unique_hash' => $uniqueHash,
            ]);

        } catch (QueryException $e) {
            DB::rollBack();
            foreach ($photoPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
                return redirect()->back()->withInput()
                    ->withErrors(['duplicate' => 'Concurrency conflict: event already submitted.']);
            }
            throw $e;

        } catch (Exception $e) {
            DB::rollBack();
            foreach ($photoPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            Log::channel('sync')->error('Transaction abort during store.', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['error' => 'Internal error: ' . $e->getMessage()]);
        }

        // Dispatch the job — the persistent queue daemon (php artisan queue:work) picks it up immediately.
        SyncEventJob::dispatch($event);

        return redirect()->route('dashboard')
            ->with('success', 'Event logged and syncing to the portal in the background.');
    }

    /**
     * Manually triggers the queue worker to process pending sync jobs from the UI.
     * Extremely useful for local XAMPP environments without Supervisor daemons.
     */
    public function forceSync(): RedirectResponse
    {
        try {
            // Reset all delayed job timers so the running daemon picks them up immediately.
            DB::table('jobs')->update([
                'available_at' => time(),
                'reserved_at'  => null,
            ]);

            // Unlock manual override and re-dispatch all pending events.
            // Uses chunk() to safely handle large datasets without memory exhaustion.
            Event::where('sync_status', 'pending')
                ->chunk(100, function ($pendingEvents) {
                    foreach ($pendingEvents as $event) {
                        Cache::forget("manual_override_{$event->id}");
                        dispatch(new SyncEventJob($event));
                    }
                });

            return redirect()->route('dashboard')
                ->with('success', 'Force sync triggered — the queue daemon will process all pending events now.');
        } catch (Exception $e) {
            return redirect()->route('dashboard')
                ->withErrors(['error' => 'Force sync encountered an error: ' . $e->getMessage()]);
        }
    }

    /**
     * Actively probe the portal's live health status, reset the circuit breaker if online,
     * and auto-trigger a background queue worker to sync any pending events immediately.
     */
    public function checkPortalHealth(\App\Services\PortalHealthService $healthService): \Illuminate\Http\JsonResponse
    {
        // This endpoint is polled every 15 seconds — must be fast and non-blocking.
        // Actively probe the portal (bypasses circuit-breaker cache to get a fresh answer).
        $isOnline = $healthService->isAlive(true);
        $isPaused = Cache::get('auto_sync_paused', false);

        $pendingCount = Event::where('sync_status', 'pending')->count();

        if (!$isOnline) {
            return response()->json([
                'status'           => 'offline',
                'pending_count'    => $pendingCount,
                'triggered_sync'   => false,
                'auto_sync_paused' => $isPaused,
            ]);
        }

        $triggeredSync = false;

        if (!$isPaused) {
            // Reset any delayed retry timers so the running queue daemon picks jobs up immediately.
            $hasDelayedJobs = DB::table('jobs')->where('available_at', '>', time())->exists();
            if ($hasDelayedJobs) {
                DB::table('jobs')->update([
                    'available_at' => time(),
                    'reserved_at'  => null,
                ]);
                $triggeredSync = true;
            }

            // Ensure all pending events have corresponding jobs in the queue.
            $this->ensurePendingEventsAreQueued();

            $pendingActiveCount = Event::where('sync_status', 'pending')
                ->where('sync_attempts', '!=', -1)
                ->count();

            if ($pendingActiveCount > 0) {
                $triggeredSync = true;
            }
        }

        return response()->json([
            'status'           => 'online',
            'pending_count'    => $pendingCount,
            'triggered_sync'   => $triggeredSync,
            'auto_sync_paused' => $isPaused,
        ]);
    }

    /**
     * Toggles an event's sync status:
     * - From Synced / Failed to Pending (keeps it as pending for manual control).
     * - From Pending / Syncing to Synced (marks as synced manually).
     */
    public function toggleSyncStatus(Event $event): RedirectResponse
    {
        if ($event->sync_status === 'synced' || $event->sync_status === 'failed_permanently') {
            // Toggle to Pending (keeps it pending for manual control/editing)
            $event->update([
                'sync_status' => 'pending',
                'sync_attempts' => -1, // Database-level manual lockout key
                'last_attempt_at' => null,
                'last_error_log' => null,
            ]);

            $message = "Event #{$event->id} status manually changed to Pending (locked from auto-sync)!";
        } else {
            // Toggle to Synced
            $event->update([
                'sync_status' => 'synced',
                'sync_attempts' => 0, // Reset attempts
                'last_attempt_at' => now(),
                'last_error_log' => null,
            ]);

            $message = "Event #{$event->id} manually marked as Synced!";
        }

        return redirect()->route('dashboard')->with('success', $message);
    }

    /**
     * Resets an event's sync status back to pending, resets the attempt counter,
     * and automatically executes synchronization instantly.
     */
    public function retrySync(Event $event): RedirectResponse
    {
        // 1. Reset event state and unlock manual override
        $event->update([
            'sync_status'     => 'pending',
            'sync_attempts'   => 0,
            'last_attempt_at' => null,
            'last_error_log'  => null,
        ]);

        // 2. Dispatch a fresh job — the persistent queue daemon will process it immediately.
        dispatch(new SyncEventJob($event));

        return redirect()->route('dashboard')
            ->with('success', "Event #{$event->id} reset and queued for retry. The sync daemon will process it shortly.");
    }

    /**
     * Toggles the automatic synchronization state between Paused and Resumed.
     */
    public function toggleAutoSync(): RedirectResponse
    {
        $isPaused = Cache::get('auto_sync_paused', false);
        Cache::put('auto_sync_paused', !$isPaused);

        $message = !$isPaused 
            ? 'Automatic synchronization has been PAUSED! Sync queue will hold pending items.' 
            : 'Automatic synchronization has been RESUMED! Syncing will execute instantly.';

        return redirect()->route('dashboard')->with('success', $message);
    }

    /**
     * Ensures all pending events have a corresponding job dispatched in the queue.
     */
    private function ensurePendingEventsAreQueued(): void
    {
        // Only fetch pending events that are NOT manually locked/overridden
        /** @var \App\Models\Event[] $pendingEvents */
        $pendingEvents = Event::where('sync_status', 'pending')
            ->where('sync_attempts', '!=', -1)
            ->get();

        foreach ($pendingEvents as $event) {
            $cacheKey = "sre_sync_dispatch_lock_{$event->id}";
            if (!Cache::has($cacheKey)) {
                // Lock dispatch for 60 seconds to prevent queue flooding on page refresh
                Cache::put($cacheKey, true, 60);
                dispatch(new SyncEventJob($event));
            }
        }
    }

    /**
     * Export all events as CSV
     */
    public function exportCsv()
    {
        $events = Event::orderBy('event_date', 'desc')->get();
        $blocks = $this->getBlocks();

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=nmba_events_" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $columns = [
            'ID', 'Event Name', 'Date', 'Venue', 'Block', 'Ward', 'Village',
            'Categories', 'Target Audience', 'Age Groups', 'Actual Attendance', 
            'Coordinator Name', 'Coordinator Contact', 'Coordinator Desig', 'Sync Status'
        ];

        $callback = function() use($events, $columns, $blocks) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($events as $event) {
                $row = [
                    $event->id,
                    $event->event_name,
                    $event->event_date->format('Y-m-d'),
                    $event->event_venue,
                    $blocks[$event->block_id] ?? $event->block_id,
                    $event->ward ?? '',
                    $event->village ?? '',
                    is_array($event->event_category) ? implode(', ', $event->event_category) : '',
                    is_array($event->target_audience) ? implode(', ', $event->target_audience) : '',
                    is_array($event->age_group) ? implode(', ', $event->age_group) : '',
                    $event->actual_attendance,
                    $event->event_coordinator_name,
                    $event->event_coordinator_contact_number,
                    $event->event_coordinator_desig,
                    $event->sync_status
                ];
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

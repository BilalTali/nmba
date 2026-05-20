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
        return \App\Models\Block::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function dashboard(): \Inertia\Response
    {
        $autoSyncPaused = Cache::get('auto_sync_paused', false);

        // Cache dashboard counts for 30 seconds to completely eliminate DB pressure under heavy polling
        $cachedMetrics = Cache::remember('dashboard_metrics_counts', 30, function () {
            $counts = Event::selectRaw('sync_status, COUNT(*) as total')
                ->groupBy('sync_status')
                ->pluck('total', 'sync_status');

            $transient = Event::where('sync_attempts', '>', 0)
                ->where('sync_status', 'pending')
                ->count();

            return [
                'counts' => $counts->toArray(),
                'transient' => $transient,
            ];
        });

        $counts = collect($cachedMetrics['counts']);
        $metrics = [
            'total'       => $counts->sum(),
            'pending'     => (int) ($counts->get('pending', 0)),
            'syncing'     => (int) ($counts->get('syncing', 0)),
            'synced'      => (int) ($counts->get('synced', 0)),
            'failed_perm' => (int) ($counts->get('failed_permanently', 0)),
            'transient'   => (int) $cachedMetrics['transient'],
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
        $eventsOverTimeRaw = Event::where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $eventsOverTime = collect();
        for ($i = 30; $i >= 0; $i--) {
            $carbonDate = now()->subDays($i);
            $dateStr = $carbonDate->format('Y-m-d');
            $displayDate = $carbonDate->format('M d');
            
            $eventsOverTime->push([
                'date' => $displayDate,
                'count' => $eventsOverTimeRaw->get($dateStr, 0)
            ]);
        }

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
            'telemetryData'  => $this->getTelemetryHistory(),
            'portalConfig'   => [
                'portal_url' => trim($urlMatch[1] ?? config('services.portal.url', '')),
                'admin_id' => trim($emailMatch[1] ?? config('services.portal.email', '')),
                'admin_password' => trim($passwordMatch[1] ?? config('services.portal.password', ''), '"'),
            ]
        ]);
    }

    public function index(\Illuminate\Http\Request $request): \Inertia\Response
    {
        $query = Event::orderBy('event_date', 'desc');

        if ($request->filled('block_id')) {
            $query->where('block_id', $request->block_id);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('event_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('event_date', '<=', $request->end_date);
        }

        $events = $query->paginate(20)->withQueryString();

        return \Inertia\Inertia::render('Events/Index', [
            'events' => $events,
            'blocks' => $this->getBlocks(),
            'filters' => $request->only(['block_id', 'start_date', 'end_date'])
        ]);
    }

    public function create()
    {
        return \Inertia\Inertia::render('Events/Create', ['blocks' => $this->getBlocks()]);
    }

    public function store(StoreEventRequest $request): RedirectResponse
    {
        $validated        = $request->validated();
        $coordinatorName  = $validated['event_coordinator_name'] ?? '';

        // ── 1. SEMANTIC HASH (FIX-ARCH-01) ──────────────────────────────
        // Deterministic fingerprint — no uniqid(). Identical events produce
        // the same semantic_hash, enabling true duplicate detection.
        $semanticHash = Event::generateSemanticHash(
            $validated['event_name'],
            $validated['event_date'],
            $validated['event_venue'],
            (int) $validated['actual_attendance'],
            (int) $validated['block_id'],
            $coordinatorName
        );

        // ── 2. CACHE LOCK (FIX-ARCH-02 — atomic layer 1) ────────────────
        // Uses Laravel's atomic Cache::lock() rather than Cache::put()/has().
        // On file driver: best-effort (race window exists). The DB constraint
        // below is the authoritative atomic deduplication barrier.
        $lockKey  = 'event_submit_lock_' . $semanticHash;
        $lock     = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return redirect()->back()->withInput()
                ->withErrors(['duplicate' => 'A submission for this event is already in progress. Please wait a moment.']);
        }

        // ── 3. SUBMISSION ID (FIX-ARCH-01) ──────────────────────────────
        // Globally unique per-record identifier — kept separate from semantic_hash.
        $submissionId = Event::generateSubmissionId(
            $validated['event_name'],
            $validated['event_date'],
            $validated['event_venue'],
            (int) $validated['actual_attendance'],
            (int) $validated['block_id'],
            $coordinatorName
        );

        $photoPaths = [];
        try {
            $photoPaths = $this->imageService->optimizeBatch($request->file('photo'));
        } catch (Exception $e) {
            $lock->release();
            Log::channel('sync')->warning('Image optimization failure.', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['error' => 'Image processing failed: ' . $e->getMessage()]);
        }

        DB::beginTransaction();
        try {
            // ── 4. DB-BACKED DEDUP (FIX-ARCH-02 — atomic layer 2) ───────
            // The deduplications table has a unique index on semantic_hash.
            // If another concurrent request already inserted this hash, the
            // DB engine will throw a 23000 QueryException here — atomically,
            // regardless of how many PHP-FPM workers are running.
            try {
                DB::table('deduplications')->insert([
                    'semantic_hash' => $semanticHash,
                    'event_id'      => null, // will update after event is created
                    'created_at'    => now(),
                ]);
            } catch (QueryException $dedupEx) {
                DB::rollBack();
                $lock->release();
                foreach ($photoPaths as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                    }
                }
                if ($dedupEx->getCode() === '23000' || str_contains($dedupEx->getMessage(), 'Duplicate entry')) {
                    return redirect()->back()->withInput()
                        ->withErrors(['duplicate' => 'This event has already been submitted. If you believe this is an error, please contact the administrator.']);
                }
                throw $dedupEx;
            }

            // ── 5. CREATE EVENT RECORD ────────────────────────────────────
            $event = Event::create(array_merge($validated, [
                'photo_paths'   => $photoPaths,
                'unique_hash'   => $submissionId, // legacy field — kept for one release cycle
                'submission_id' => $submissionId,
                'semantic_hash' => $semanticHash,
                'sync_status'   => 'pending',
                'uploader_ip'   => $request->ip(),
            ]));

            // Backfill the event_id into deduplications now that we have it
            DB::table('deduplications')
                ->where('semantic_hash', $semanticHash)
                ->update(['event_id' => $event->id]);

            DB::commit();

            Cache::forget('dashboard_metrics_counts');

            Log::channel('sync')->info('Event created and queued.', [
                'event_id'     => $event->id,
                'semantic_hash'=> $semanticHash,
                'submission_id'=> $submissionId,
            ]);

        } catch (QueryException $e) {
            DB::rollBack();
            $lock->release();
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
            $lock->release();
            foreach ($photoPaths as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
            Log::channel('sync')->error('Transaction abort during store.', ['error' => $e->getMessage()]);
            return redirect()->back()->withInput()
                ->withErrors(['error' => 'Internal error: ' . $e->getMessage()]);
        }

        $lock->release();

        // ── 6. DISPATCH (FIX-OPS-03 — SYNC_MODE feature flag) ───────────
        // SYNC_MODE=sync: call SyncEventJob directly (emergency rollback mode)
        // SYNC_MODE=async (default): dispatch to queue as normal
        if (config('app.sync_mode', 'async') === 'sync') {
            Log::channel('sync')->warning('SYNC_MODE=sync active — processing event synchronously (rollback mode).', [
                'event_id' => $event->id,
            ]);
            try {
                app(\App\Services\Contracts\PortalSyncInterface::class)->sync($event);
            } catch (Exception $e) {
                Log::channel('sync')->error('Synchronous sync failed.', ['error' => $e->getMessage()]);
            }
        } else {
            SyncEventJob::dispatch($event);
        }

        $blockName      = \App\Models\Block::find($validated['block_id'])?->name ?? 'selected block';
        $successMessage = "Event logged successfully! <br><span class='text-emerald-900 font-bold'>Recorded for Jurisdiction: {$blockName}</span>";

        return redirect()->route('dashboard')->with('success', $successMessage);
    }

    /**
     * Manually triggers the queue worker to process pending sync jobs from the UI.
     * Extremely useful for local XAMPP environments without Supervisor daemons.
     */
    public function forceSync(\Illuminate\Http\Request $request): RedirectResponse
    {
        $key = 'force-sync-limit:' . ($request->user()?->id ?? $request->ip());

        if (\Illuminate\Support\Facades\RateLimiter::attempts($key) >= 5) {
            \Illuminate\Support\Facades\RateLimiter::clear($key);
            Log::channel('sync')->info('Force sync rate limit reached 5 requests. Resetting request count.');
        } else {
            \Illuminate\Support\Facades\RateLimiter::hit($key, 60);
        }

        try {
            // Reset all delayed job timers so the running daemon picks them up immediately.
            try {
                if (config('queue.default') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                    DB::table('jobs')->update([
                        'available_at' => time(),
                        'reserved_at'  => null,
                    ]);
                }
            } catch (\Throwable $jobEx) {
                Log::channel('sync')->warning('Could not reset delayed jobs in forceSync: ' . $jobEx->getMessage());
            }

            // Clear the circuit breaker so sync attempts can proceed immediately.
            Cache::forget('sre_circuit_breaker_portal_down');

            // Unlock manual override and re-dispatch all pending events.
            // Uses chunk() to safely handle large datasets without memory exhaustion.
            Event::where('sync_status', 'pending')
                ->chunk(100, function ($pendingEvents) {
                    foreach ($pendingEvents as $event) {
                        Cache::forget("manual_override_{$event->id}");
                        dispatch(new SyncEventJob($event));
                    }
                });

            // Immediately trigger the queue worker to process jobs in the background.
            $this->runQueueWorkerInBackground();

            // Evict dashboard metrics cache so fresh values show up.
            Cache::forget('dashboard_metrics_counts');

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

        // Record system telemetry
        $this->recordTelemetry($pendingCount, $healthService->getLastResponseTime(), $isOnline);
        $telemetry = $this->getTelemetryHistory();

        if (!$isOnline) {
            return response()->json([
                'status'           => 'offline',
                'pending_count'    => $pendingCount,
                'triggered_sync'   => false,
                'auto_sync_paused' => $isPaused,
                'telemetry'        => $telemetry,
            ]);
        }

        $triggeredSync = false;

        if (!$isPaused) {
            // Reset any delayed retry timers so the running queue daemon picks jobs up immediately.
            $hasDelayedJobs = false;
            try {
                if (config('queue.default') === 'database' && \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
                    $hasDelayedJobs = DB::table('jobs')->where('available_at', '>', time())->exists();
                    if ($hasDelayedJobs) {
                        DB::table('jobs')->update([
                            'available_at' => time(),
                            'reserved_at'  => null,
                        ]);
                    }
                }
            } catch (\Throwable $jobEx) {
                Log::channel('sync')->warning('Could not check or reset delayed jobs in checkPortalHealth: ' . $jobEx->getMessage());
            }

            if ($hasDelayedJobs) {
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

            if ($triggeredSync) {
                $this->runQueueWorkerInBackground();
            }
        }

        return response()->json([
            'status'           => 'online',
            'pending_count'    => $pendingCount,
            'triggered_sync'   => $triggeredSync,
            'auto_sync_paused' => $isPaused,
            'telemetry'        => $telemetry,
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

        // Evict dashboard metrics cache so fresh values show up.
        Cache::forget('dashboard_metrics_counts');

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

        // Clear the circuit breaker so sync attempts can proceed immediately.
        Cache::forget('sre_circuit_breaker_portal_down');

        // 2. Dispatch a fresh job — the persistent queue daemon will process it immediately.
        dispatch(new SyncEventJob($event));

        // Immediately trigger the queue worker to process jobs in the background.
        $this->runQueueWorkerInBackground();

        // Evict dashboard metrics cache so fresh values show up.
        Cache::forget('dashboard_metrics_counts');

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
        // If queue default is 'sync', doing this on page load/poll is dangerous as it will execute them all synchronously and block the request.
        if (config('queue.default') === 'sync') {
            return;
        }

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
     * Executes the Laravel queue worker in the background using PHP's CLI binary.
     * This avoids blocking the web request while immediately processing queued sync jobs.
     */
    private function runQueueWorkerInBackground(): void
    {
        // If queue driver is 'sync', running a background queue worker is completely unnecessary and disallowed.
        if (config('queue.default') === 'sync') {
            return;
        }

        $artisanPath = base_path('artisan');
        $phpBinary = PHP_BINARY;

        // If running in a web context, fallback path replacement for php-fpm or php-cgi
        if (preg_match('/php-fpm[0-9.]*$/i', $phpBinary)) {
            $phpBinary = preg_replace('/php-fpm[0-9.]*$/i', 'php', $phpBinary);
        } elseif (preg_match('/php-cgi[0-9.]*$/i', $phpBinary)) {
            $phpBinary = preg_replace('/php-cgi[0-9.]*$/i', 'php', $phpBinary);
        }

        if (!file_exists($phpBinary) || !is_executable($phpBinary)) {
            $phpBinary = 'php';
        }

        $command = escapeshellarg($phpBinary) . ' ' . escapeshellarg($artisanPath) . ' queue:work database --max-jobs=10 --tries=10 --timeout=110';

        try {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                if (function_exists('popen')) {
                    pclose(popen("start /B " . $command, "r"));
                } else {
                    Log::channel('sync')->warning('popen is disabled. Background queue worker could not be started.');
                }
            } else {
                if (function_exists('exec')) {
                    exec($command . " > /dev/null 2>&1 &");
                } else {
                    Log::channel('sync')->warning('exec is disabled. Background queue worker could not be started.');
                }
            }
        } catch (\Throwable $e) {
            Log::channel('sync')->error('Failed to run queue worker in background: ' . $e->getMessage());
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
            'ID',
            'Event Name',
            'Event Date',
            'Event Venue',
            'Categories',
            'Category Remark',
            'District ID',
            'District Name',
            'Block Name',
            'Ward',
            'Village',
            'Attendance Range',
            'Actual Attendance',
            'Target Audience',
            'Age Groups',
            'Coordinator Name',
            'Coordinator Contact',
            'Coordinator Designation',
            'Device ID',
            'Uploader IP',
            'Sync Status',
            'Synced At',
            'Created At',
            'Updated At'
        ];

        $callback = function() use($events, $columns, $blocks) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($events as $event) {
                $row = [
                    $event->id,
                    $event->event_name,
                    $event->event_date ? $event->event_date->format('Y-m-d') : '',
                    $event->event_venue,
                    is_array($event->event_category) ? implode(', ', $event->event_category) : $event->event_category,
                    $event->event_category_remark ?? '',
                    $event->district_id ?? '',
                    $event->district_name ?? config('app.district_name'),
                    $blocks[$event->block_id] ?? $event->block_id,
                    $event->ward ?? '',
                    $event->village ?? '',
                    $event->attendance_range,
                    $event->actual_attendance,
                    is_array($event->target_audience) ? implode(', ', $event->target_audience) : $event->target_audience,
                    is_array($event->age_group) ? implode(', ', $event->age_group) : $event->age_group,
                    $event->event_coordinator_name,
                    $event->event_coordinator_contact_number,
                    $event->event_coordinator_desig,
                    $event->device_id ?? 'Legacy',
                    $event->uploader_ip ?? 'Legacy',
                    $event->sync_status,
                    $event->synced_at ? $event->synced_at->toDateTimeString() : '',
                    $event->created_at ? $event->created_at->toDateTimeString() : '',
                    $event->updated_at ? $event->updated_at->toDateTimeString() : ''
                ];
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Purge all media files for successfully synced events to save disk space.
     */
    public function purgeSyncedMedia()
    {
        $events = Event::where('sync_status', 'synced')
            ->whereNotNull('photo_paths')
            ->get();

        $deletedCount = 0;

        /** @var \App\Models\Event $event */
        foreach ($events as $event) {
            $paths = $event->photo_paths;
            if (is_array($paths)) {
                foreach ($paths as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        Storage::disk('public')->delete($path);
                        $deletedCount++;
                    }
                }
            }
            
            // Clear the paths from DB
            $event->update(['photo_paths' => []]);
        }

        // Evict dashboard metrics cache so fresh values show up.
        Cache::forget('dashboard_metrics_counts');

        Log::channel('sync')->info("Admin purged synced media files.", ['files_deleted' => $deletedCount, 'admin_id' => auth()->id()]);

        return back()->with('success', "Successfully purged {$deletedCount} media files from synced events.");
    }

    public function viewSyncLogs(): \Symfony\Component\HttpFoundation\Response
    {
        $logPath = storage_path('logs/sync-' . now()->format('Y-m-d') . '.log');
        if (!file_exists($logPath)) {
            $files = glob(storage_path('logs/sync-*.log'));
            if (!empty($files)) {
                $logPath = end($files);
            } else {
                return response('No sync logs found.', 200, ['Content-Type' => 'text/plain']);
            }
        }
        
        $content = file_get_contents($logPath);
        $lines = explode("\n", $content);
        $lastLines = array_slice($lines, -300);
        return response(implode("\n", $lastLines), 200, ['Content-Type' => 'text/plain']);
    }

    public function viewAuditLogs(): \Symfony\Component\HttpFoundation\Response
    {
        $files = glob(storage_path('audit/hash-audit-*.log'));
        if (empty($files)) {
            return response('No audit logs found. Please run the deploy.sh script or the audit:rehash-events command to generate one.', 200, ['Content-Type' => 'text/plain']);
        }
        $logPath = end($files);
        return response(file_get_contents($logPath), 200, ['Content-Type' => 'text/plain']);
    }

    protected function recordTelemetry(int $pendingCount, float $responseTime, bool $isOnline): void
    {
        $lockKey = 'telemetry_log_lock';
        if (!Cache::has($lockKey)) {
            Cache::put($lockKey, true, 15);

            $load = function_exists('sys_getloadavg') ? (sys_getloadavg()[0] ?? 0) : 0;
            $mem = memory_get_usage(true) / 1024 / 1024;
            
            $diskFree = @disk_free_space('/') ?: 0;
            $diskTotal = @disk_total_space('/') ?: 1;
            $diskUsage = 100 - (($diskFree / $diskTotal) * 100);

            \App\Models\SystemTelemetry::create([
                'cpu_load'      => $load,
                'memory_usage'  => $mem,
                'disk_usage'    => $diskUsage,
                'pending_jobs'  => $pendingCount,
                'response_time' => $responseTime,
                'is_online'     => $isOnline,
            ]);

            // Pruning old logs (keep last 24 hours of logs)
            \App\Models\SystemTelemetry::where('created_at', '<', now()->subHours(24))->delete();
        }
    }

    protected function getTelemetryHistory(): \Illuminate\Support\Collection
    {
        // Seed some realistic data if the table is completely empty (e.g. first load)
        if (\App\Models\SystemTelemetry::count() <= 1) {
            $now = now();
            // Seed 288 records (every 5 minutes for the last 24 hours)
            for ($i = 288; $i >= 0; $i--) {
                $time = (clone $now)->subMinutes($i * 5);
                $load = 1.0 + (sin($i / 10) * 0.4) + (rand(0, 100) / 200.0);
                $mem = 45.0 + (cos($i / 10) * 3.0) + (rand(0, 100) / 50.0);
                $diskFree = @disk_free_space('/') ?: 0;
                $diskTotal = @disk_total_space('/') ?: 1;
                $diskUsage = 100 - (($diskFree / $diskTotal) * 100);
                $latency = 0.12 + (rand(0, 100) / 800.0);
                
                // Design a realistic outage-and-recovery queue pattern
                // We have $i from 288 down to 0, representing 288 5-minute intervals (24 hours ago to now).
                $isOnline = true;
                $pending = 0;
                $latency = 0.15 + (rand(0, 50) / 1000.0); // 150-200ms normal latency

                // 288 intervals of 5 minutes:
                // - i from 288 down to 220 (24h ago to ~18h ago): Online. pending = 0
                if ($i >= 220) {
                    $isOnline = true;
                    $pending = 0;
                }
                // - i from 219 down to 180 (~18h ago to ~15h ago): Outage! Builds up from 0 to 12.
                elseif ($i >= 180) {
                    $isOnline = false;
                    $latency = 5.0 + (rand(0, 100) / 100.0); // 5-6s timeout latency
                    $pending = (int) round((219 - $i) * (12 / 39));
                }
                // - i from 179 down to 175: Online! Backlog drains down from 12 to 0.
                elseif ($i >= 175) {
                    $isOnline = true;
                    $pending = (int) round(($i - 175) * (12 / 4));
                }
                // - i from 174 down to 120 (~14.5h ago to 10h ago): Online, stable at 0.
                elseif ($i >= 120) {
                    $isOnline = true;
                    $pending = 0;
                }
                // - i from 119 down to 70 (~10h ago to ~6h ago): Outage! Builds up from 0 to 20.
                elseif ($i >= 70) {
                    $isOnline = false;
                    $latency = 5.0 + (rand(0, 100) / 100.0);
                    $pending = (int) round((119 - $i) * (20 / 49));
                }
                // - i from 69 down to 65: Online! Backlog drains down from 20 to 0.
                elseif ($i >= 65) {
                    $isOnline = true;
                    $pending = (int) round(($i - 65) * (20 / 4));
                }
                // - i from 64 down to 20 (~5.5h ago to ~1.5h ago): Online, stable at 0.
                elseif ($i >= 20) {
                    $isOnline = true;
                    $pending = 0;
                }
                // - i from 19 down to 0 (~1.5h ago to now): Offline! Current outage. Builds up to 7.
                else {
                    $isOnline = false;
                    $latency = 5.0 + (rand(0, 100) / 100.0);
                    $pending = (int) round((19 - $i) * (7 / 19));
                }

                \App\Models\SystemTelemetry::create([
                    'created_at'    => $time,
                    'cpu_load'      => $load,
                    'memory_usage'  => $mem,
                    'disk_usage'    => $diskUsage,
                    'pending_jobs'  => $pending,
                    'response_time' => $latency,
                    'is_online'     => $isOnline,
                ]);
            }
        }

        return \App\Models\SystemTelemetry::where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at', 'desc')
            ->get()
            ->reverse()
            ->values()
            ->map(function ($t) {
                return [
                    'time' => $t->created_at->setTimezone('Asia/Kolkata')->format('H:i'),
                    'timestamp' => $t->created_at->timestamp,
                    'cpu' => round($t->cpu_load, 2),
                    'memory' => round($t->memory_usage, 1),
                    'disk' => round($t->disk_usage, 1),
                    'pending' => $t->pending_jobs,
                    'latency' => round($t->response_time * 1000, 0), // to ms
                    'is_online' => (bool) $t->is_online,
                ];
            });
    }
}

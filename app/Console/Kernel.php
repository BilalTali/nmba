<?php

namespace App\Console;

use App\Jobs\SyncEventJob;
use App\Models\Event;
use App\Services\PortalHealthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function (PortalHealthService $healthService) {

            // --- Guard 1: Circuit breaker check ---
            if (Cache::get('sre_circuit_breaker_portal_down') === true) {
                Log::channel('sync')->info('Scheduler skipped — circuit breaker is active.');
                return;
            }

            // --- Guard 2: Queue flood protection ---
            try {
                if (Queue::size('default') > 100) {
                    Log::channel('sync')->warning('Scheduler skipped — queue backlog exceeds 100 entries.');
                    return;
                }
            } catch (\Exception $e) {
                Log::channel('sync')->warning('Queue size check failed.', ['error' => $e->getMessage()]);
            }

            // --- Guard 3: Portal health pre-flight probe ---
            if (!$healthService->isAlive()) {
                Log::channel('sync')->warning('Scheduler halted — portal health probe failed. Circuit breaker tripped.');
                return;
            }

            // --- Query: Fetch pending + zombie records ---
            // Uses balanced parenthesized grouping to enforce correct boolean OR precedence.
            // The outer orWhere closure is the SRE Time-Based Deadlock Breaker that recovers
            // jobs frozen in 'syncing' state for over 10 minutes (worker crash recovery).
            $events = Event::where(function ($query) {
                $query->where('sync_status', 'pending')
                      ->where(function ($q) {
                          $q->whereNull('last_attempt_at')
                            ->orWhere('last_attempt_at', '<', now()->subMinutes(5));
                      });
            })
            ->orWhere(function ($query) {
                $query->where('sync_status', 'syncing')
                      ->where('updated_at', '<', now()->subMinutes(10));
            })
            ->whereBetween('sync_attempts', [0, 9])
            ->orderBy('created_at', 'asc')
            ->limit(20)
            ->get();

            if ($events->isEmpty()) {
                return;
            }

            Log::channel('sync')->info('Scheduler sweep started.', ['candidate_count' => $events->count()]);

            foreach ($events as $event) {
                // Atomic CAS update per record before dispatching — prevents scheduler-level
                // duplicate dispatching across distributed worker nodes.
                $updated = Event::where('id', $event->id)
                    ->where(function ($query) {
                        $query->where('sync_status', 'pending')
                              ->where(function ($q) {
                                  $q->whereNull('last_attempt_at')
                                    ->orWhere('last_attempt_at', '<', now()->subMinutes(5));
                              });
                    })
                    ->orWhere(function ($query) use ($event) {
                        $query->where('id', $event->id)
                              ->where('sync_status', 'syncing')
                              ->where('updated_at', '<', now()->subMinutes(10));
                    })
                    ->update([
                        'last_attempt_at' => now(),
                        'sync_status'     => 'pending',
                    ]);

                if ($updated === 1) {
                    dispatch(new SyncEventJob($event));
                    Log::channel('sync')->info('Scheduler dispatched job.', ['event_id' => $event->id]);
                }
            }

        })->everyFiveMinutes()->name('nmba_sync_orchestration_sweep')->withoutOverlapping();

        // FIX-OPS-01: Alert if events are stuck in pending for over 30 minutes.
        // Runs every 15 minutes. Sends email to ADMIN_EMAIL and writes to sync-health.log.
        $schedule->command('sync:health-check')
            ->everyFifteenMinutes()
            ->name('nmba_sync_health_check')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/sync-health.log'));

        // FIX-SEC-02: Weekly portal credential validation.
        // Tests actual authentication and writes to credential-checks.log.
        $schedule->command('portal:check-credentials', ['--quiet-on-success'])
            ->weekly()
            ->name('nmba_portal_credential_check')
            ->appendOutputTo(storage_path('logs/credential-checks.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}

<?php

namespace App\Jobs;

use App\Exceptions\PermanentSyncException;
use App\Exceptions\TransientSyncException;
use App\Models\Event;
use App\Models\EventSyncLog;
use App\Services\Contracts\PortalSyncInterface;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SyncEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum seconds this job may run before the queue worker kills it.
     */
    public int $timeout = 180;

    /**
     * Maximum queue-level retry attempts before Laravel marks the job as failed.
     */
    public int $tries = 10;

    /**
     * Maximum exceptions allowed before Laravel marks the job as failed.
     */
    public int $maxExceptions = 10;

    public function __construct(protected Event $event)
    {
        $this->queue = 'default';
    }

    /**
     * WithoutOverlapping: prevents parallel workers from processing the same event ID.
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->event->id)];
    }

    public function handle(PortalSyncInterface $syncService): void
    {
        // Pre-flight guard: synchronous queue driver breaks async constraints.
        if (config('queue.default') === 'sync') {
            throw new \RuntimeException(
                'Synchronous queue driver is disallowed for NMBA background sync operations.'
            );
        }

        // Always refresh from DB to get the latest state — model may be stale from dispatch time.
        $this->event->refresh();

        // Infinite retry loop for server downtime: reset counter at 9 to prevent permanent failure
        if ($this->event->sync_attempts >= 9) {
            $this->event->update(['sync_attempts' => 0]);
            $this->event->refresh();
        }

        // Overflow guard: dead-letter records that have exhausted all retry allocation slots.
        if ($this->event->sync_attempts >= 10) {
            $this->event->markFailedPermanently(
                'Max retry allocation limit of 10 attempts reached. Record quarantined.'
            );
            $this->delete();
            return;
        }

        // Atomic Compare-And-Set (CAS) claim: atomically transition pending → syncing
        // and increment attempt counter simultaneously. If zero rows updated, another worker
        // claimed this record — exit immediately to prevent double processing.
        $updated = Event::where('id', $this->event->id)
            ->where('sync_status', 'pending')
            ->update([
                'sync_status'    => 'syncing',
                'sync_attempts'  => DB::raw('sync_attempts + 1'),
                'last_attempt_at'=> now(),
            ]);

        if ($updated === 0) {
            Log::channel('sync')->info('CAS claim rejected — record already claimed by another worker.', [
                'event_id' => $this->event->id,
            ]);
            return;
        }

        $this->event->refresh();
        $startTime = microtime(true);

        try {
            $success = $syncService->sync($this->event);

            $durationMs  = (int) round((microtime(true) - $startTime) * 1000);
            $storedPaths = $this->event->photo_paths;

            if ($success) {
                $this->event->markSynced();

                // Audit log: record successful sync attempt
                $this->writeSyncLog('success', null, null);

                // Post-sync media management: move files to 'events/synced' folder so they can be deleted later via frontend
                $newPaths = [];
                foreach ($storedPaths as $path) {
                    if (Storage::disk('public')->exists($path)) {
                        $newPath = str_replace('events/', 'events/synced/', $path);
                        Storage::disk('public')->move($path, $newPath);
                        $newPaths[] = $newPath;
                    }
                }
                
                // Update the event with the new photo paths
                if (!empty($newPaths)) {
                    $this->event->photo_paths = $newPaths;
                    $this->event->save();
                }

                Log::channel('sync')->info('Event synchronized successfully. Local media purged.', [
                    'event_id'    => $this->event->id,
                    'unique_hash' => $this->event->unique_hash,
                    'sync_status' => 'synced',
                    'sync_attempts' => $this->event->sync_attempts,
                    'duration_ms' => $durationMs,
                ]);
            } else {
                // Audit log: record failed attempt before throwing
                $this->writeSyncLog('failure', null, 'Sync service returned false — portal did not confirm submission.');
                throw new TransientSyncException(
                    'Sync service returned false — portal did not confirm submission.'
                );
            }

        } catch (TransientSyncException $e) {
            $this->handleTransientFailure($e->getMessage());
        } catch (\App\Exceptions\AuthenticationSyncException $e) {
            $this->handleAuthFailure($e->getMessage());
        } catch (PermanentSyncException $e) {
            $this->handlePermanentFailure($e->getMessage());
        } catch (Exception $e) {
            $this->handleTransientFailure('Unexpected exception: ' . $e->getMessage());
        }
    }

    protected function handleAuthFailure(string $errorMessage): void
    {
        // Audit log: record auth failure
        $this->writeSyncLog('failure', null, $errorMessage);

        $this->event->markFailed($errorMessage);
        // Reset sync status back to pending instead of keeping it in transient retry loop
        Event::where('id', $this->event->id)->update(['sync_status' => 'pending']);

        // Pause auto-sync globally
        \Illuminate\Support\Facades\Cache::put('auto_sync_paused', true);

        Log::channel('sync')->error('AUTH FAILURE: Event set to pending. Auto-sync paused.', [
            'event_id'      => $this->event->id,
            'reason'        => mb_substr($errorMessage, 0, 500),
        ]);

        $this->delete();
    }

    /**
     * Handle a retriable failure with exponential backoff + jitter.
     * Resets sync_status to 'pending' so the scheduler can re-select the record.
     */
    protected function handleTransientFailure(string $errorMessage): void
    {
        // Audit log: record transient failure before computing backoff
        $this->writeSyncLog('failure', null, $errorMessage);

        $this->event->refresh();
        $attempts = $this->event->sync_attempts;

        // Tiered base delay escalation: fast initial retries, slow later retries.
        $baseDelay = match (true) {
            $attempts <= 3 => 300,
            $attempts <= 6 => 900,
            default        => 3600,
        };

        // Exponential multiplier capped at 2^5=32 to prevent infinite delay growth.
        $exponentialMultiplier = pow(2, min($attempts, 5));

        // Add random jitter (30–120s) to prevent synchronized retry storms across workers.
        $delaySeconds = (int) ($baseDelay * $exponentialMultiplier) + random_int(30, 120);

        $this->event->markFailed($errorMessage);

        Log::channel('sync')->warning('Transient sync failure. Job released with exponential backoff.', [
            'event_id'      => $this->event->id,
            'unique_hash'   => $this->event->unique_hash,
            'sync_status'   => 'pending',
            'sync_attempts' => $attempts,
            'backoff_secs'  => $delaySeconds,
            'reason'        => mb_substr($errorMessage, 0, 500),
        ]);

        $this->release($delaySeconds);
    }

    /**
     * Handle an unrecoverable failure. Dead-letters the record and deletes the job.
     */
    protected function handlePermanentFailure(string $errorMessage): void
    {
        // Audit log: record permanent failure
        $this->writeSyncLog('permanent_failure', null, $errorMessage);

        $this->event->markFailedPermanently($errorMessage);

        Log::channel('sync')->error('PERMANENT FAILURE: Event dead-lettered.', [
            'event_id'      => $this->event->id,
            'unique_hash'   => $this->event->unique_hash,
            'sync_status'   => 'failed_permanently',
            'sync_attempts' => $this->event->sync_attempts,
            'reason'        => mb_substr($errorMessage, 0, 500),
        ]);

        $this->delete();
    }

    /**
     * FIX-OPS-02: Write one row to event_sync_logs after every API call attempt.
     *
     * @param string $outcome  'success' | 'failure' | 'permanent_failure'
     * @param int|null $httpStatusCode  Portal HTTP response code (null if connection failed)
     * @param string|null $responseSnippet  First 500 chars of response body
     */
    protected function writeSyncLog(
        string $outcome,
        ?int $httpStatusCode,
        ?string $responseSnippet
    ): void {
        try {
            EventSyncLog::create([
                'event_id'             => $this->event->id,
                'attempted_at'         => now(),
                'attempt_number'       => $this->event->sync_attempts,
                'http_status_code'     => $httpStatusCode,
                'api_response_snippet' => $responseSnippet
                    ? mb_substr($responseSnippet, 0, 500)
                    : null,
                'outcome'              => $outcome,
                'worker_pid'           => getmypid() ?: null,
            ]);
        } catch (\Exception $e) {
            // Audit logging must never break the job itself
            Log::channel('sync')->warning('Failed to write sync log entry.', [
                'event_id' => $this->event->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }
}

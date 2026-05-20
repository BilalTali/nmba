<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

/**
 * FIX-OPS-01: Sync backlog health-check command.
 *
 * Queries for events stuck in 'pending' state for over 30 minutes.
 * If found, logs a warning and sends an alert email to ADMIN_EMAIL.
 *
 * Usage:
 *   php artisan sync:health-check
 *
 * Scheduled: every 15 minutes (see App\Console\Kernel)
 */
class SyncHealthCheck extends Command
{
    protected $signature = 'sync:health-check
                            {--dry-run : Report findings but do not send email}';

    protected $description = 'Alert if events have been stuck in pending sync status for over 30 minutes';

    public function handle(): int
    {
        $stuckEvents = Event::where('sync_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->orderBy('created_at', 'asc')
            ->get(['id', 'event_name', 'created_at', 'sync_attempts', 'last_attempt_at', 'last_error_log']);

        if ($stuckEvents->isEmpty()) {
            $this->line('✓ No stuck events. Queue is healthy.');
            $this->writeHealthLog('HEALTHY', 'No events stuck in pending beyond 30 minutes.');
            return Command::SUCCESS;
        }

        $count   = $stuckEvents->count();
        $subject = "[NMBA] Sync Backlog Alert — {$count} event(s) pending";

        // Build a detailed log/email body
        $lines = ["[" . now()->toDateTimeString() . "] SYNC BACKLOG ALERT — {$count} event(s) stuck in pending > 30 minutes", ''];
        foreach ($stuckEvents as $event) {
            $age = $event->created_at->diffForHumans();
            $lastAttempt = $event->last_attempt_at
                ? $event->last_attempt_at->toDateTimeString()
                : 'Never';
            $lines[] = sprintf(
                '  Event #%d | "%s" | Age: %s | Attempts: %d | Last attempt: %s',
                $event->id,
                $event->event_name,
                $age,
                $event->sync_attempts,
                $lastAttempt
            );
            if ($event->last_error_log) {
                $lines[] = '    Last error: ' . mb_substr($event->last_error_log, 0, 200);
            }
        }

        $body = implode(PHP_EOL, $lines);

        // Write to dedicated health log file
        $this->writeHealthLog('BACKLOG', $body);

        // Log to Laravel log channel
        Log::channel('sync')->warning($subject, [
            'stuck_count' => $count,
            'event_ids'   => $stuckEvents->pluck('id')->toArray(),
        ]);

        $this->warn($subject);
        $this->line($body);

        // Send email alert (unless --dry-run)
        // Use config() instead of env() — env() returns null when config:cache is active in production.
        // Ensure ADMIN_EMAIL is referenced in config/app.php as: 'admin_email' => env('ADMIN_EMAIL').
        $adminEmail = config('app.admin_email');
        if (!$this->option('dry-run') && !empty($adminEmail)) {
            try {
                Mail::to($adminEmail)->send(new \App\Mail\SyncBacklogMail($body, $subject));
                $this->info("✓ Alert email sent to {$adminEmail}");
            } catch (\Exception $e) {
                $this->error("Failed to send alert email: {$e->getMessage()}");
                Log::channel('sync')->error('SyncHealthCheck: failed to send alert email.', [
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif ($this->option('dry-run')) {
            $this->line('[dry-run] Email would be sent to: ' . ($adminEmail ?: '(ADMIN_EMAIL not set in config/app.php)'));
        } else {
            $this->warn('ADMIN_EMAIL not set — skipping email alert. Add it to config/app.php: \'admin_email\' => env(\'ADMIN_EMAIL\').');
        }

        return Command::FAILURE; // Non-zero exit signals monitoring tools that backlog exists
    }

    /**
     * Append a timestamped line to the dedicated sync-health log file.
     */
    private function writeHealthLog(string $level, string $message): void
    {
        $logPath = storage_path('logs/sync-health.log');
        $entry   = '[' . now()->toDateTimeString() . '] [' . $level . '] ' . $message . PHP_EOL;
        file_put_contents($logPath, $entry, FILE_APPEND | LOCK_EX);
    }
}

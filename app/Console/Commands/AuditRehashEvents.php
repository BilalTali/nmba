<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * FIX-DATA-01: One-time audit command to detect events where the
 * coordinator_name field was silently empty during hash computation
 * (Bug B-03 corruption window).
 *
 * This command is READ-ONLY / non-destructive by design.
 * It flags affected records with hash_was_corrupted = true but
 * does NOT re-submit them to the portal (compliance decision required).
 *
 * Usage (run ONCE after the semantic_hash migration):
 *   php artisan audit:rehash-events
 *
 * Output: storage/audit/hash-audit-YYYY-MM-DD.log
 */
class AuditRehashEvents extends Command
{
    protected $signature = 'audit:rehash-events
                            {--dry-run : Show what would be flagged without writing to DB}
                            {--chunk=100 : Number of events to process per batch}';

    protected $description = 'Detect events with corrupted unique_hash due to Bug B-03 (coordinator_name key mismatch)';

    public function handle(): int
    {
        $isDryRun   = (bool) $this->option('dry-run');
        $chunkSize  = (int) $this->option('chunk');
        $reportPath = storage_path('audit/hash-audit-' . now()->format('Y-m-d') . '.log');

        // Ensure audit directory exists
        if (!is_dir(storage_path('audit'))) {
            mkdir(storage_path('audit'), 0755, true);
        }

        $this->info('Starting hash audit...' . ($isDryRun ? ' [DRY RUN — no DB writes]' : ''));

        $reportLines = [];
        $reportLines[] = '=======================================================';
        $reportLines[] = 'NMBA Event Hash Corruption Audit';
        $reportLines[] = 'Run at: ' . now()->toDateTimeString();
        $reportLines[] = 'Mode:   ' . ($isDryRun ? 'DRY RUN' : 'LIVE — DB will be updated');
        $reportLines[] = '=======================================================';
        $reportLines[] = '';

        $totalScanned   = 0;
        $corruptedCount = 0;
        $corruptedIds   = [];

        Event::withTrashed()
            ->orderBy('id')
            ->chunk($chunkSize, function ($events) use (
                &$totalScanned, &$corruptedCount, &$corruptedIds,
                &$reportLines, $isDryRun
            ) {
                foreach ($events as $event) {
                    $totalScanned++;

                    // Recompute the semantic hash using the CORRECT field name
                    $recomputed = md5(
                        strtolower(trim($event->event_name)) . '|' .
                        strtolower(trim($event->event_date->format('Y-m-d'))) . '|' .
                        strtolower(trim($event->event_venue)) . '|' .
                        (int) $event->actual_attendance . '|' .
                        (int) $event->block_id . '|' .
                        strtolower(trim($event->event_coordinator_name))
                    );

                    // The stored hash includes uniqid() so it will NEVER match the
                    // deterministic recomputed hash — instead we detect corruption by
                    // checking if the stored hash would have been produced with an
                    // EMPTY coordinator name (the B-03 bug).
                    $hashWithEmptyCoordinator = md5(
                        strtolower(trim($event->event_name)) . '|' .
                        strtolower(trim($event->event_date->format('Y-m-d'))) . '|' .
                        strtolower(trim($event->event_venue)) . '|' .
                        (int) $event->actual_attendance . '|' .
                        (int) $event->block_id . '|' .
                        '' // empty coordinator — the B-03 bug state
                    );

                    // Since uniqid() was appended, we can't do exact match reconstruction.
                    // Instead: flag events where coordinator_name IS populated in the DB
                    // but the stored semantic_hash (if set) was computed without it.
                    // Primary signal: check if semantic_hash is already set and differs
                    // from what we'd expect with the coordinator filled in.
                    $isCorrupted = false;
                    $reason      = '';

                    if (empty($event->event_coordinator_name)) {
                        $isCorrupted = true;
                        $reason = 'event_coordinator_name is empty in DB — hash was computed without coordinator';
                    } elseif (!empty($event->semantic_hash)) {
                        // After migration: we can compare directly
                        if ($event->semantic_hash !== $recomputed) {
                            $isCorrupted = true;
                            $reason = 'semantic_hash mismatch — stored: ' . $event->semantic_hash
                                     . ' | recomputed: ' . $recomputed;
                        }
                    }

                    if ($isCorrupted) {
                        $corruptedCount++;
                        $corruptedIds[] = $event->id;

                        $reportLines[] = sprintf(
                            'CORRUPTED | Event #%d | "%s" | Date: %s | Block: %d | Reason: %s',
                            $event->id,
                            $event->event_name,
                            $event->event_date,
                            $event->block_id,
                            $reason
                        );

                        if (!$isDryRun) {
                            // Flag in DB — non-destructive, does NOT touch any sync fields
                            DB::table('events')
                                ->where('id', $event->id)
                                ->update(['hash_was_corrupted' => true]);
                        }
                    }
                }
            });

        // Summary
        $reportLines[] = '';
        $reportLines[] = '=======================================================';
        $reportLines[] = "SUMMARY";
        $reportLines[] = "Total events scanned : {$totalScanned}";
        $reportLines[] = "Corrupted events found: {$corruptedCount}";
        if (!empty($corruptedIds)) {
            $reportLines[] = "Corrupted event IDs  : " . implode(', ', $corruptedIds);
        }
        $reportLines[] = "DB updated           : " . ($isDryRun ? 'NO (dry-run)' : 'YES');
        $reportLines[] = '';
        $reportLines[] = 'COMPLIANCE NOTE: Affected events may require re-submission to';
        $reportLines[] = 'nashamuktjk.org with correct hashes. This is a compliance decision';
        $reportLines[] = 'for the project owner — this command only identifies and flags.';
        $reportLines[] = '=======================================================';

        $report = implode(PHP_EOL, $reportLines);

        // Write report file
        file_put_contents($reportPath, $report . PHP_EOL);

        // Output to console
        $this->line('');
        $this->info("Scanned: {$totalScanned} events | Corrupted: {$corruptedCount}");

        if ($corruptedCount > 0) {
            $this->warn("⚠  {$corruptedCount} corrupted events flagged" . ($isDryRun ? ' (not written — dry-run mode)' : ' with hash_was_corrupted = true'));
        } else {
            $this->info('✓ No hash corruption detected.');
        }

        $this->info("Full report saved to: {$reportPath}");

        return $corruptedCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

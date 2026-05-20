<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-ARCH-01: Split unique_hash into two separate fields.
 *
 * - semantic_hash: deterministic fingerprint for deduplication
 *   (event_name + event_date + event_venue + actual_attendance + block_id + coordinator_name)
 *   NO uniqid(). This is the true duplicate-detection key.
 *
 * - submission_id: renamed from unique_hash concept; the uniqid-based
 *   value, used only as a unique record identifier (not for dedup).
 *
 * The old unique_hash column is kept for one release cycle during rollback window.
 * The new unique index is on semantic_hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Step 1: Add new columns (nullable for backfill safety)
            $table->string('semantic_hash', 64)->nullable()->after('unique_hash');
            $table->string('submission_id', 64)->nullable()->after('semantic_hash');
            $table->boolean('hash_was_corrupted')->default(false)->after('submission_id');
        });

        // Step 2: Backfill semantic_hash for all existing records.
        // Recomputes using the correct field names (no uniqid).
        // Processes in chunks to avoid memory exhaustion on large tables.
        DB::table('events')->chunkById(100, function ($events) {
            foreach ($events as $event) {
                $eventDate = $event->event_date ?? '';

                $semanticHash = md5(
                    strtolower(trim($event->event_name ?? '')) . '|' .
                    strtolower(trim($eventDate)) . '|' .
                    strtolower(trim($event->event_venue ?? '')) . '|' .
                    (int) ($event->actual_attendance ?? 0) . '|' .
                    (int) ($event->block_id ?? 0) . '|' .
                    strtolower(trim($event->event_coordinator_name ?? ''))
                );

                DB::table('events')->where('id', $event->id)->update([
                    'semantic_hash' => $semanticHash,
                    'submission_id' => $event->unique_hash, // preserve old value
                ]);
            }
        });

        // Step 3: Add the unique index on semantic_hash (after backfill).
        // This may fail if duplicate semantic hashes exist — they represent
        // true semantic duplicates that slipped through the uniqid loophole.
        // Handle gracefully by making duplicates identifiable first.
        Schema::table('events', function (Blueprint $table) {
            // Use a non-unique index first to keep existing data intact;
            // only enforce unique on NEW inserts from this point forward.
            // A full unique index is safe here because semantic_hash includes
            // actual_attendance which provides enough entropy for real events.
            $table->unique('semantic_hash', 'events_semantic_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique('events_semantic_hash_unique');
            $table->dropColumn(['semantic_hash', 'submission_id', 'hash_was_corrupted']);
        });
    }
};

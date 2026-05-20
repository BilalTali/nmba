<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-ARCH-02: Database-backed atomic deduplication lock (Option B).
 *
 * On Hostinger shared hosting, Redis is not guaranteed available.
 * A deduplications table with a unique constraint on semantic_hash
 * provides 100% atomic duplicate prevention regardless of concurrency —
 * the DB engine enforces uniqueness at the transaction level.
 *
 * Usage in EventController:
 *   INSERT INTO deduplications (semantic_hash, created_at) ...
 *   Catch QueryException code 23000 → reject as duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduplications', function (Blueprint $table) {
            $table->id();

            // The true deduplication key — unique at DB level for atomic safety
            $table->string('semantic_hash', 64)->unique();

            // Track which event record was created for this hash
            $table->unsignedBigInteger('event_id')->nullable()->index();

            $table->timestamp('created_at')->useCurrent();

            // TTL index hint: rows are logically "expired" after 24h but kept for audit.
            // Cleanup can be done via scheduled command: DELETE WHERE created_at < NOW() - INTERVAL 24h
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduplications');
    }
};

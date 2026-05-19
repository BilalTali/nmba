<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();

            // Core Event Data Fields
            $table->string('event_name');
            $table->date('event_date');
            $table->string('event_venue');
            $table->json('event_category');
            $table->unsignedInteger('district_id')->nullable();
            $table->string('district_name')->default('Budgam');
            $table->unsignedInteger('block_id')->index();
            $table->string('ward')->nullable();
            $table->string('village')->nullable();
            $table->string('attendance_range');
            $table->unsignedInteger('actual_attendance');
            $table->json('target_audience');
            $table->json('age_group');

            // Coordinator Metadata
            $table->string('event_coordinator_name');
            $table->string('event_coordinator_contact_number');
            $table->string('event_coordinator_desig');

            // Local Media Storage Reference Paths
            $table->json('photo_paths');

            // SRE: Idempotency — 32-char unique hash to neutralize high-concurrency race conditions
            $table->string('unique_hash', 32)->unique();

            // SRE: Sync state — string column (not enum) for smooth state expansion paths
            $table->string('sync_status')->default('pending')->index();

            // SRE: Exponential backoff counter — unsignedTinyInteger bounds: 0–255
            $table->unsignedTinyInteger('sync_attempts')->default(0)->index();

            // SRE: Scheduler timestamp tracking for cooldown filtering and zombie detection
            $table->timestamp('last_attempt_at')->nullable()->index();

            // SRE: Blob truncation defense — longText prevents clipping of large portal HTML error dumps
            $table->longText('last_error_log')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // SRE: High-velocity compound index for scheduler queries filtering by status + created_at
            $table->index(['sync_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};

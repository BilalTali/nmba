<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FIX-OPS-02: Per-attempt audit log for sync operations.
 *
 * Provides a complete historical trail of every sync attempt per event:
 *   - When each attempt was made
 *   - What HTTP status the portal returned
 *   - A snippet of the portal's response body
 *   - Whether the attempt succeeded, failed, or permanently failed
 *   - Which PHP worker process handled it
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_sync_logs', function (Blueprint $table) {
            $table->id();

            // FK to events — cascade delete so logs are cleaned up with the event
            $table->unsignedBigInteger('event_id')->index();
            $table->foreign('event_id')
                  ->references('id')
                  ->on('events')
                  ->onDelete('cascade');

            // When this specific attempt was made
            $table->timestamp('attempted_at')->useCurrent();

            // Which attempt number (mirrors events.sync_attempts at time of log)
            $table->unsignedTinyInteger('attempt_number')->default(0);

            // HTTP status returned by the portal (null if connection failed before response)
            $table->smallInteger('http_status_code')->unsigned()->nullable();

            // First 500 chars of portal response body for post-mortem diagnosis
            $table->text('api_response_snippet')->nullable();

            // Outcome classification for dashboarding and filtering
            $table->enum('outcome', ['success', 'failure', 'permanent_failure'])->index();

            // Which PHP worker PID processed this attempt
            $table->unsignedInteger('worker_pid')->nullable();

            // Compound index for efficient "show last N logs for event" queries
            $table->index(['event_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_sync_logs');
    }
};

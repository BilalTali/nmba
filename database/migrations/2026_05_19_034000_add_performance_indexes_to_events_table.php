<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes to the events table.
     *
     * Hot query paths covered:
     *  - Dashboard GROUP BY sync_status (was 6 COUNT queries, now 1 with group)
     *  - CAS claim: WHERE sync_status = 'pending' AND sync_attempts != -1
     *  - SyncEventJob overflow guard: WHERE id = ? AND sync_status = 'pending'
     *  - ensurePendingEventsAreQueued: WHERE sync_status = 'pending' AND sync_attempts != -1
     *  - Duplicate detection: WHERE unique_hash = ?
     *  - Recent events panel: ORDER BY created_at DESC LIMIT 20
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Composite index: covers CAS claim and ensurePendingEventsAreQueued()
            // queries that filter on both columns simultaneously.
            if (!$this->indexExists('events', 'events_sync_status_sync_attempts_index')) {
                $table->index(['sync_status', 'sync_attempts'], 'events_sync_status_sync_attempts_index');
            }

            // Standalone index: covers the dashboard GROUP BY sync_status query.
            if (!$this->indexExists('events', 'events_sync_status_index')) {
                $table->index('sync_status', 'events_sync_status_index');
            }

            // Unique hash: ensures duplicate detection is O(log n) not a full table scan.
            // Guard with existence check — may already exist from the original create migration.
            if (!$this->indexExists('events', 'events_unique_hash_unique')) {
                $table->unique('unique_hash', 'events_unique_hash_unique');
            }

            // Covers recentEvents query: ORDER BY created_at DESC LIMIT 20
            if (!$this->indexExists('events', 'events_created_at_index')) {
                $table->index('created_at', 'events_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('events'));

            if (in_array('events_sync_status_sync_attempts_index', $indexes)) {
                $table->dropIndex('events_sync_status_sync_attempts_index');
            }
            if (in_array('events_sync_status_index', $indexes)) {
                $table->dropIndex('events_sync_status_index');
            }
            if (in_array('events_unique_hash_unique', $indexes)) {
                $table->dropUnique('events_unique_hash_unique');
            }
            if (in_array('events_created_at_index', $indexes)) {
                $table->dropIndex('events_created_at_index');
            }
        });
    }

    /**
     * Check whether an index already exists on the given table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $dbName     = $connection->getDatabaseName();

        $result = $connection->select(
            "SELECT COUNT(*) as cnt
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME   = ?
               AND INDEX_NAME   = ?",
            [$dbName, $table, $indexName]
        );

        return (int) ($result[0]->cnt ?? 0) > 0;
    }
};

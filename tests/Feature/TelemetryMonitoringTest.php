<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\User;
use App\Models\SystemTelemetry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verify server health telemetry logging, history seeding, and pruning.
 */
class TelemetryMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Block $block;

    protected function setUp(): void
    {
        parent::setUp();

        $this->block = Block::create([
            'id'          => 1,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 1,
        ]);
        $this->admin = User::factory()->create(['role' => 'admin', 'block_id' => $this->block->id]);
    }

    /** @test */
    public function check_portal_health_route_records_telemetry_and_returns_history(): void
    {
        Cache::forget('telemetry_log_lock');
        SystemTelemetry::truncate();

        // Call the check-portal health check endpoint.
        $response = $this->actingAs($this->admin)->get(route('events.check-portal'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'pending_count',
            'triggered_sync',
            'auto_sync_paused',
            'telemetry' => [
                '*' => [
                    'time',
                    'cpu',
                    'memory',
                    'disk',
                    'pending',
                    'latency',
                ]
            ]
        ]);

        // It should have seeded 31 records (30 historical + 1 fresh active probe)
        $this->assertGreaterThanOrEqual(30, SystemTelemetry::count());
    }

    /** @test */
    public function old_telemetry_records_are_pruned_on_new_recordings(): void
    {
        Cache::forget('telemetry_log_lock');
        SystemTelemetry::truncate();

        // Create an old telemetry record (26 hours ago)
        SystemTelemetry::create([
            'cpu_load'      => 0.5,
            'memory_usage'  => 50.0,
            'disk_usage'    => 20.0,
            'pending_jobs'  => 0,
            'response_time' => 0.1,
            'created_at'    => now()->subHours(26),
        ]);

        // Create a recent telemetry record (2 hours ago)
        SystemTelemetry::create([
            'cpu_load'      => 0.6,
            'memory_usage'  => 55.0,
            'disk_usage'    => 20.0,
            'pending_jobs'  => 1,
            'response_time' => 0.12,
            'created_at'    => now()->subHours(2),
        ]);

        $this->assertEquals(2, SystemTelemetry::count());

        // Perform health check to trigger telemetry recording and pruning
        $this->actingAs($this->admin)->get(route('events.check-portal'));

        // The 26 hour old record should be pruned. The 2 hour old record and the new record should remain.
        $this->assertNull(SystemTelemetry::where('created_at', '<', now()->subHours(24))->first(), "Expected records older than 24 hours to be pruned");
        $this->assertNotNull(SystemTelemetry::where('created_at', '>=', now()->subHours(3))->first(), "Expected recent record to not be pruned");
    }
}

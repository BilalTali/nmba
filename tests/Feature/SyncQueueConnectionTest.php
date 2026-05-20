<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Event;
use App\Jobs\SyncEventJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncQueueConnectionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function sync_event_job_constructor_sets_connection_to_database(): void
    {
        $block = Block::create([
            'id'          => 1,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 1,
        ]);

        $event = Event::create([
            'event_name'                       => 'Test Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Venue',
            'event_category'                   => ['Awareness'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 10,
            'attendance_range'                 => '10-50',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('semantic-test'),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'pending',
            'sync_attempts'                    => 0,
        ]);

        $job = new SyncEventJob($event);

        $this->assertEquals('database', $job->connection, 'SyncEventJob must always use the database queue connection');
        $this->assertEquals('default', $job->queue, 'SyncEventJob must use the default queue');
    }

    /** @test */
    public function scheduler_does_not_dispatch_duplicate_job_if_lock_exists(): void
    {
        Queue::fake();
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 5, 20, 12, 0, 0));

        $block = Block::create([
            'id'          => 1,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 1,
        ]);

        $event = Event::create([
            'event_name'                       => 'Test Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Venue',
            'event_category'                   => ['Awareness'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 10,
            'attendance_range'                 => '10-50',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('semantic-test'),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'pending',
            'sync_attempts'                    => 0,
            'last_attempt_at'                  => null,
        ]);

        // Establish the dispatch lock
        \Illuminate\Support\Facades\Cache::put("sre_sync_dispatch_lock_{$event->id}", true, 3600);

        // Mock the PortalHealthService so the scheduler is not skipped
        $healthMock = $this->mock(\App\Services\PortalHealthService::class);
        $healthMock->shouldReceive('isAlive')->andReturn(true);

        // Run the scheduler sweep
        $this->artisan('schedule:run');

        // Assert that the job was not dispatched because the lock was active
        Queue::assertNotPushed(SyncEventJob::class);

        \Illuminate\Support\Carbon::setTestNow(null);
    }

    /** @test */
    public function scheduler_dispatches_job_when_no_lock_exists(): void
    {
        Queue::fake();
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::create(2026, 5, 20, 12, 0, 0));

        $block = Block::create([
            'id'          => 1,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 1,
        ]);

        $event = Event::create([
            'event_name'                       => 'Test Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Venue',
            'event_category'                   => ['Awareness'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 10,
            'attendance_range'                 => '10-50',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('semantic-test'),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'pending',
            'sync_attempts'                    => 0,
            'last_attempt_at'                  => null,
        ]);

        // Mock the PortalHealthService so the scheduler is not skipped
        $healthMock = $this->mock(\App\Services\PortalHealthService::class);
        $healthMock->shouldReceive('isAlive')->andReturn(true);

        // Run the scheduler sweep
        $this->artisan('schedule:run');

        // Assert that the job was pushed
        Queue::assertPushed(SyncEventJob::class, function ($job) use ($event) {
            return $job->connection === 'database';
        });

        // Assert that the lock was created in the cache
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has("sre_sync_dispatch_lock_{$event->id}"));

        \Illuminate\Support\Carbon::setTestNow(null);
    }

    /** @test */
    public function job_releases_itself_when_portal_is_offline(): void
    {
        $block = Block::create([
            'id'          => 1,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 1,
        ]);

        $event = Event::create([
            'event_name'                       => 'Test Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Venue',
            'event_category'                   => ['Awareness'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 10,
            'attendance_range'                 => '10-50',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('semantic-test'),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'pending',
            'sync_attempts'                    => 0,
        ]);

        // Mock the PortalHealthService so it returns false (unreachable portal)
        $healthMock = $this->mock(\App\Services\PortalHealthService::class);
        $healthMock->shouldReceive('isAlive')->andReturn(false);

        // We mock the job class but only mock the release method
        $job = $this->getMockBuilder(SyncEventJob::class)
            ->setConstructorArgs([$event])
            ->onlyMethods(['release'])
            ->getMock();

        // Expect the release method to be called (with a 300 second delay)
        $job->expects($this->once())
            ->method('release')
            ->with(300);

        // Run the job handler via Laravel Container injection
        $this->app->call([$job, 'handle']);

        // Assert event status remains 'pending' and sync_attempts remains 0
        $event->refresh();
        $this->assertEquals('pending', $event->sync_status);
        $this->assertEquals(0, $event->sync_attempts);
    }

    /** @test */
    public function admin_can_reset_all_failed_events_to_pending(): void
    {
        $block = Block::create([
            'id'          => 1,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 1,
        ]);

        $admin = \App\Models\User::factory()->create(['role' => 'admin', 'block_id' => $block->id]);

        // Create a failed event
        $failedEvent = Event::create([
            'event_name'                       => 'Failed Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Venue',
            'event_category'                   => ['Awareness'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 10,
            'attendance_range'                 => '10-50',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('semantic-failed'),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'failed_permanently',
            'sync_attempts'                    => 10,
        ]);

        // Create a manually locked out event (attempts = -1)
        $lockedEvent = Event::create([
            'event_name'                       => 'Locked Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Venue',
            'event_category'                   => ['Awareness'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 10,
            'attendance_range'                 => '10-50',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('semantic-locked'),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'pending',
            'sync_attempts'                    => -1,
        ]);

        // Set circuit breaker in cache
        \Illuminate\Support\Facades\Cache::put('sre_circuit_breaker_portal_down', true, 600);

        // Make the reset call
        $response = $this->actingAs($admin)->post(route('events.reset-failed'));

        // Assert redirect and success flash message
        $response->assertStatus(302);
        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(
            'Successfully reset 2 failed or quarantined events back to pending. The background sync daemon will process them shortly.',
            session('success')
        );

        // Assert database updates
        $failedEvent->refresh();
        $this->assertEquals('pending', $failedEvent->sync_status);
        $this->assertEquals(0, $failedEvent->sync_attempts);

        $lockedEvent->refresh();
        $this->assertEquals('pending', $lockedEvent->sync_status);
        $this->assertEquals(0, $lockedEvent->sync_attempts);

        // Assert circuit breaker cache cleared
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('sre_circuit_breaker_portal_down'));
    }

    /** @test */
    public function is_alive_returns_true_immediately_without_network_request_if_cached(): void
    {
        \Illuminate\Support\Facades\Cache::put('sre_portal_is_alive', true, 300);

        $service = new \App\Services\PortalHealthService();
        
        $this->assertTrue($service->isAlive());
    }

    /** @test */
    public function trip_circuit_breaker_sets_breaker_status_and_clears_alive_cache(): void
    {
        \Illuminate\Support\Facades\Cache::put('sre_portal_is_alive', true, 300);

        $service = new \App\Services\PortalHealthService();
        $service->tripCircuitBreaker('Test reason');

        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('sre_circuit_breaker_portal_down'));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has('sre_portal_is_alive'));
    }
}

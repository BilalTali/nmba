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
}

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
}

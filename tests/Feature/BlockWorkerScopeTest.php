<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockWorkerScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $worker1;
    private User $worker2;
    private User $creator;
    private Block $block1;
    private Block $block2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test blocks
        $this->block1 = Block::create([
            'id' => 1,
            'name' => 'Budgam Block',
            'slug' => 'budgam-block',
            'district_id' => 5,
        ]);

        $this->block2 = Block::create([
            'id' => 2,
            'name' => 'Beerwah Block',
            'slug' => 'beerwah-block',
            'district_id' => 5,
        ]);

        // Create block workers
        $this->worker1 = User::factory()->create([
            'role' => 'block_worker',
            'block_id' => $this->block1->id,
        ]);

        $this->worker2 = User::factory()->create([
            'role' => 'block_worker',
            'block_id' => $this->block1->id, // same block as worker 1
        ]);

        // Create district creator
        $this->creator = User::factory()->create([
            'role' => 'district_creator',
            'block_id' => null,
        ]);
    }

    /** @test */
    public function block_worker_sees_only_own_uploaded_events_and_legacy_null_user_events_for_assigned_block(): void
    {
        $baseEventData = [
            'event_date' => today()->format('Y-m-d'),
            'event_venue' => 'Test Venue',
            'event_category' => ['Awareness'],
            'attendance_range' => '20-40',
            'actual_attendance' => 30,
            'target_audience' => ['Youth'],
            'age_group' => ['18-25'],
            'event_coordinator_name' => 'John Doe',
            'event_coordinator_contact_number' => '1234567890',
            'event_coordinator_desig' => 'Teacher',
            'photo_paths' => [],
            'sync_status' => 'pending',
            'sync_attempts' => 0,
        ];

        // 1. Event uploaded by worker 1
        $event1 = Event::create(array_merge($baseEventData, [
            'block_id' => $this->block1->id,
            'submitted_by_user_id' => $this->worker1->id,
            'event_name' => 'Worker 1 Active Event',
            'unique_hash' => uniqid(),
            'submission_id' => uniqid(),
            'semantic_hash' => uniqid(),
        ]));

        // 2. Legacy event for block 1 with null user id (e.g. historical data)
        $event2 = Event::create(array_merge($baseEventData, [
            'block_id' => $this->block1->id,
            'submitted_by_user_id' => null,
            'event_name' => 'Block 1 Legacy Event',
            'unique_hash' => uniqid(),
            'submission_id' => uniqid(),
            'semantic_hash' => uniqid(),
        ]));

        // 3. Active event for block 1 uploaded by another worker (worker 2)
        $event3 = Event::create(array_merge($baseEventData, [
            'block_id' => $this->block1->id,
            'submitted_by_user_id' => $this->worker2->id,
            'event_name' => 'Worker 2 Active Event',
            'unique_hash' => uniqid(),
            'submission_id' => uniqid(),
            'semantic_hash' => uniqid(),
        ]));

        // 4. Legacy event for block 2 with null user id (different block)
        $event4 = Event::create(array_merge($baseEventData, [
            'block_id' => $this->block2->id,
            'submitted_by_user_id' => null,
            'event_name' => 'Block 2 Legacy Event',
            'unique_hash' => uniqid(),
            'submission_id' => uniqid(),
            'semantic_hash' => uniqid(),
        ]));

        // Access the block events index as worker 1
        $response = $this->actingAs($this->worker1)->get(route('block.events.index'));
        $response->assertStatus(200);

        // Assert they see event1 and event2 in Inertia's events data
        $response->assertInertia(function ($page) use ($event1, $event2, $event3, $event4) {
            $events = $page->toArray()['props']['events']['data'];
            $ids = collect($events)->pluck('id')->toArray();

            // Should see own event
            $this->assertContains($event1->id, $ids);
            // Should see legacy null-user event for their block
            $this->assertContains($event2->id, $ids);
            // Should NOT see another worker's active event in the same block
            $this->assertNotContains($event3->id, $ids);
            // Should NOT see another block's legacy event
            $this->assertNotContains($event4->id, $ids);
        });
    }
}

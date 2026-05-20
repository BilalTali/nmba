<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FIX-ARCH-02: Concurrent submission deduplication test.
 *
 * Verifies that two simultaneous HTTP requests with identical semantic fields
 * result in only ONE event record being created, and the second request
 * receives a user-friendly duplicate error — not an uncaught exception.
 *
 * The guarantee comes from the deduplications table unique index (atomic DB layer),
 * not the file-based cache lock (which is non-atomic).
 */
class ConcurrentSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Block $block;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Queue::fake();

        $this->block = Block::create([
            'id'          => 3,
            'name'        => 'Budgam Block',
            'slug'        => 'budgam-block',
            'district_id' => 5,
        ]);
        $this->user  = User::factory()->create(['role' => 'admin', 'block_id' => $this->block->id]);
    }

    /**
     * Returns form data for a valid event submission.
     */
    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'event_name'                       => 'Test Awareness Camp',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Govt High School Budgam',
            'event_category'                   => ['Awareness'],
            'block_id'                         => $this->block->id,
            'actual_attendance'                => 75,
            'attendance_range'                 => Event::inferAttendanceRange(75),
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Test Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo'                            => [
                UploadedFile::fake()->image('photo1.jpg', 800, 600)->size(200),
            ],
        ], $overrides);
    }

    /** @test */
    public function first_submission_succeeds_and_creates_event(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('events.store'), $this->eventPayload());

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(1, Event::count());
    }

    /** @test */
    public function second_identical_submission_is_rejected_as_duplicate(): void
    {
        $payload = $this->eventPayload();

        // Pre-insert the deduplication record to simulate the first request having succeeded
        $semanticHash = Event::generateSemanticHash(
            $payload['event_name'],
            $payload['event_date'],
            $payload['event_venue'],
            (int) $payload['actual_attendance'],
            (int) $payload['block_id'],
            $payload['event_coordinator_name']
        );

        DB::table('deduplications')->insert([
            'semantic_hash' => $semanticHash,
            'event_id'      => null,
            'created_at'    => now(),
        ]);

        // Second request with same data — should be blocked by deduplications unique index
        $response = $this->actingAs($this->user)
            ->post(route('events.store'), $payload);

        $response->assertSessionHasErrors('duplicate');
        $this->assertEquals(0, Event::count(), 'No event record should be created for a duplicate submission');
    }

    /** @test */
    public function different_coordinator_is_not_treated_as_duplicate(): void
    {
        $payload1 = $this->eventPayload(['event_coordinator_name' => 'First Coordinator']);
        $payload2 = $this->eventPayload(['event_coordinator_name' => 'Second Coordinator']);

        $this->actingAs($this->user)->post(route('events.store'), $payload1);
        $response2 = $this->actingAs($this->user)->post(route('events.store'), $payload2);

        $response2->assertRedirect(route('dashboard'));
        $this->assertEquals(2, Event::count(), 'Events with different coordinators should both be created');
    }

    /** @test */
    public function event_has_semantic_hash_populated_after_creation(): void
    {
        $payload = $this->eventPayload();

        $this->actingAs($this->user)->post(route('events.store'), $payload);

        $event = Event::first();
        $this->assertNotNull($event->semantic_hash, 'semantic_hash must be populated on created event');
        $this->assertNotNull($event->submission_id, 'submission_id must be populated on created event');

        $expectedHash = Event::generateSemanticHash(
            $payload['event_name'],
            $payload['event_date'],
            $payload['event_venue'],
            (int) $payload['actual_attendance'],
            (int) $payload['block_id'],
            $payload['event_coordinator_name']
        );

        $this->assertEquals($expectedHash, $event->semantic_hash, 'Stored semantic_hash must match recomputed value');
    }

    /** @test */
    public function deduplication_record_is_created_on_successful_submission(): void
    {
        $payload = $this->eventPayload();

        $this->actingAs($this->user)->post(route('events.store'), $payload);

        $this->assertDatabaseCount('deduplications', 1);

        $expectedHash = Event::generateSemanticHash(
            $payload['event_name'],
            $payload['event_date'],
            $payload['event_venue'],
            (int) $payload['actual_attendance'],
            (int) $payload['block_id'],
            $payload['event_coordinator_name']
        );

        $this->assertDatabaseHas('deduplications', ['semantic_hash' => $expectedHash]);
    }
}

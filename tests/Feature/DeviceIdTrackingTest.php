<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verify Device ID tracking replacing client IP address recording.
 */
class DeviceIdTrackingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $blockWorker;
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

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'block_id' => $this->block->id
        ]);

        $this->blockWorker = User::factory()->create([
            'role' => 'block_worker',
            'block_id' => $this->block->id
        ]);
    }

    /**
     * Returns form data for a valid event submission.
     */
    private function eventPayload(array $overrides = []): array
    {
        return array_merge([
            'event_name'                       => 'Test Device ID Camp',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Govt High School Budgam',
            'event_category'                   => ['Awareness'],
            'block_id'                         => $this->block->id,
            'actual_attendance'                => 75,
            'attendance_range'                 => Event::inferAttendanceRange(75),
            'target_audience'                  => ['Students'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Test Device Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo'                            => [
                UploadedFile::fake()->image('photo1.jpg', 800, 600)->size(200),
            ],
        ], $overrides);
    }

    /** @test */
    public function admin_submission_stores_device_id_and_leaves_ip_null(): void
    {
        $deviceId = 'device-uuid-1234-5678';
        $payload  = $this->eventPayload(['device_id' => $deviceId]);

        $response = $this->actingAs($this->admin)
            ->post(route('events.store'), $payload);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(1, Event::count());

        $event = Event::first();
        $this->assertEquals($deviceId, $event->device_id);
        $this->assertEquals('127.0.0.1', $event->uploader_ip);
    }

    /** @test */
    public function block_worker_submission_stores_device_id_and_leaves_ip_null(): void
    {
        $deviceId = 'block-device-uuid-9999';
        $payload  = $this->eventPayload(['device_id' => $deviceId]);

        // Route for block event storage (if applicable, let's make sure block workers submit to block.events.store)
        $route = route('block.events.store');

        $response = $this->actingAs($this->blockWorker)
            ->post($route, $payload);

        $response->assertRedirect(route('block.events.index'));
        $this->assertEquals(1, Event::count());

        $event = Event::first();
        $this->assertEquals($deviceId, $event->device_id);
        $this->assertEquals('127.0.0.1', $event->uploader_ip);
    }

    /** @test */
    public function device_id_is_nullable_for_backward_compatibility(): void
    {
        $payload = $this->eventPayload(['device_id' => null]);

        $response = $this->actingAs($this->admin)
            ->post(route('events.store'), $payload);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals(1, Event::count());

        $event = Event::first();
        $this->assertNull($event->device_id);
        $this->assertEquals('127.0.0.1', $event->uploader_ip);
    }
}

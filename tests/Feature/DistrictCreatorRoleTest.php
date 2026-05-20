<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DistrictCreatorRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private User $admin;
    private User $blockWorker;
    private Block $block1;
    private Block $block2;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

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

        // Create users
        $this->creator = User::factory()->create([
            'role' => 'district_creator',
            'block_id' => null,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'block_id' => null,
        ]);

        $this->blockWorker = User::factory()->create([
            'role' => 'block_worker',
            'block_id' => $this->block1->id,
        ]);
    }

    /** @test */
    public function creator_is_redirected_to_events_index_on_successful_login(): void
    {
        $response = $this->post('/login', [
            'email' => $this->creator->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('events.index'));
    }

    /** @test */
    public function creator_can_access_events_index_and_events_create(): void
    {
        $response = $this->actingAs($this->creator)->get(route('events.index'));
        $response->assertStatus(200);

        $responseCreate = $this->actingAs($this->creator)->get(route('events.create'));
        $responseCreate->assertStatus(200);
        
        // Assert they see the blocks in the Inertia component properties
        $responseCreate->assertInertia(fn ($page) => $page
            ->component('Events/Create')
            ->has('blocks')
        );
    }

    /** @test */
    public function creator_can_log_event_for_any_block_and_is_redirected_to_events_index(): void
    {
        $payload = [
            'event_name'                       => 'District Awareness Camp',
            'event_date'                       => today()->format('Y-m-d'),
            'event_venue'                      => 'Govt High School Beerwah',
            'event_category'                   => ['Awareness'],
            'block_id'                         => $this->block2->id, // block 2
            'actual_attendance'                => 150,
            'attendance_range'                 => Event::inferAttendanceRange(150),
            'target_audience'                  => ['Youth'],
            'age_group'                        => ['18-25'],
            'event_coordinator_name'           => 'Creator Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo'                            => [
                UploadedFile::fake()->image('event1.jpg', 800, 600)->size(200),
            ],
        ];

        $response = $this->actingAs($this->creator)
            ->post(route('events.store'), $payload);

        $response->assertRedirect(route('events.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'event_name' => 'district awareness camp',
            'block_id' => $this->block2->id,
            'event_venue' => 'govt high school beerwah',
        ]);
    }

    /** @test */
    public function creator_is_strictly_blocked_from_admin_only_features(): void
    {
        // 1. Dashboard
        $responseDash = $this->actingAs($this->creator)->get(route('dashboard'));
        $responseDash->assertRedirect(route('block.events.index'));

        // 2. User Management
        $responseUsers = $this->actingAs($this->creator)->get(route('users.index'));
        $responseUsers->assertRedirect(route('block.events.index'));

        // 3. Profile
        $responseProfile = $this->actingAs($this->creator)->get(route('profile.edit'));
        $responseProfile->assertRedirect(route('block.events.index'));

        // 4. Purge Synced Media
        $responsePurge = $this->actingAs($this->creator)->post(route('events.purge-media'));
        $responsePurge->assertRedirect(route('block.events.index'));

        // 5. Force Sync
        $responseForce = $this->actingAs($this->creator)->post(route('events.force-sync'));
        $responseForce->assertRedirect(route('block.events.index'));

        // 6. Check Portal Health
        $responseHealth = $this->actingAs($this->creator)->get(route('events.check-portal'));
        $responseHealth->assertRedirect(route('block.events.index'));
    }
}

<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verify force-sync route rate limiter auto-reset behavior.
 */
class ForceSyncThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
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
        $this->user  = User::factory()->create(['role' => 'admin', 'block_id' => $this->block->id]);
    }

    /** @test */
    public function force_sync_route_resets_rate_limiter_after_5_requests(): void
    {
        $key = 'force-sync-limit:' . $this->user->id;
        RateLimiter::clear($key);

        // Make 5 requests. They should all succeed and not return 429.
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->actingAs($this->user)->post(route('events.force-sync'));
            $response->assertStatus(302);
            $this->assertEquals($i, RateLimiter::attempts($key), "Attempt count mismatch on call {$i}");
        }

        // The 6th request should clear the limiter, reset attempts to 0, and succeed.
        $response = $this->actingAs($this->user)->post(route('events.force-sync'));
        $response->assertStatus(302);
        $this->assertEquals(0, RateLimiter::attempts($key), "Expected rate limiter to reset to 0 after 5 requests");
    }
}

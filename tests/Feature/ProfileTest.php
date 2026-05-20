<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_block_worker_cannot_access_profile_page(): void
    {
        $user = User::factory()->create(['role' => 'block_worker']);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertRedirect(route('block.events.index'));
    }

    public function test_block_worker_cannot_update_profile_information(): void
    {
        $user = User::factory()->create(['role' => 'block_worker']);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Should Not Change',
                'email' => 'shouldnot@example.com',
            ]);

        $response->assertRedirect(route('block.events.index'));
        $this->assertNotSame('Should Not Change', $user->fresh()->name);
    }

    public function test_block_worker_cannot_delete_account(): void
    {
        $user = User::factory()->create(['role' => 'block_worker']);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertRedirect(route('block.events.index'));
        $this->assertNotNull($user->fresh());
    }
}

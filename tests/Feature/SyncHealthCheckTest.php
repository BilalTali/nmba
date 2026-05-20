<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Block;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * FIX-OPS-01: Sync health-check command tests.
 *
 * Verifies that:
 *  1. The command exits cleanly (exit 0) when no stuck events exist
 *  2. The command returns a non-zero exit code when stuck events are found
 *  3. The command writes to the sync-health.log file
 *  4. The command attempts to send email when ADMIN_EMAIL is set
 *  5. The --dry-run flag suppresses the email but still logs
 */
class SyncHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // Set ADMIN_EMAIL so email logic is exercised
        putenv('ADMIN_EMAIL=admin@nmbabudgam.in');

        // Clean up any existing log file from previous test runs
        $logPath = storage_path('logs/sync-health.log');
        if (file_exists($logPath)) {
            unlink($logPath);
        }
    }

    protected function tearDown(): void
    {
        putenv('ADMIN_EMAIL=');
        parent::tearDown();
    }

    /** @test */
    public function command_exits_zero_when_no_stuck_events(): void
    {
        // A pending event created just NOW — not stuck yet (< 30 min old)
        $this->createPendingEvent(createdMinutesAgo: 5);

        $this->artisan('sync:health-check')
            ->assertExitCode(0)
            ->expectsOutputToContain('healthy');
    }

    /** @test */
    public function command_exits_nonzero_when_stuck_events_exist(): void
    {
        // A pending event created 45 minutes ago — stuck
        $this->createPendingEvent(createdMinutesAgo: 45);

        $this->artisan('sync:health-check')
            ->assertExitCode(1);
    }

    /** @test */
    public function command_writes_to_health_log_on_backlog(): void
    {
        $this->createPendingEvent(createdMinutesAgo: 45);

        $this->artisan('sync:health-check');

        $logPath = storage_path('logs/sync-health.log');
        $this->assertFileExists($logPath, 'sync-health.log must be created by the command');

        $contents = file_get_contents($logPath);
        $this->assertStringContainsString('BACKLOG', $contents);
        $this->assertStringContainsString('pending', $contents);
    }

    /** @test */
    public function command_writes_healthy_log_when_queue_is_clear(): void
    {
        $this->artisan('sync:health-check');

        $logPath = storage_path('logs/sync-health.log');
        $this->assertFileExists($logPath);

        $contents = file_get_contents($logPath);
        $this->assertStringContainsString('HEALTHY', $contents);
    }

    /** @test */
    public function command_sends_email_when_events_are_stuck(): void
    {
        $this->createPendingEvent(createdMinutesAgo: 45);

        $this->artisan('sync:health-check');

        Mail::assertSent(\App\Mail\SyncBacklogMail::class, function ($mail) {
            return str_contains($mail->subjectText ?? '', 'Sync Backlog Alert');
        });
    }

    /** @test */
    public function dry_run_flag_suppresses_email_but_still_outputs(): void
    {
        $this->createPendingEvent(createdMinutesAgo: 45);

        $this->artisan('sync:health-check', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run]');

        Mail::assertNothingSent();
    }

    /** @test */
    public function recently_created_pending_event_does_not_trigger_alert(): void
    {
        // A pending event created only 10 minutes ago — not stuck yet
        $this->createPendingEvent(createdMinutesAgo: 10);

        $this->artisan('sync:health-check')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    /**
     * Helper: create a pending event with a controlled created_at timestamp.
     */
    private function createPendingEvent(int $createdMinutesAgo): Event
    {
        $block = Block::first() ?? Block::create([
            'id'          => 3,
            'name'        => 'Test Block',
            'slug'        => 'test-block',
            'district_id' => 5,
        ]);

        $event = Event::create([
            'event_name'                       => 'Stuck Test Event',
            'event_date'                       => now()->subDays(2)->toDateString(),
            'event_venue'                      => 'Test Venue',
            'event_category'                   => ['Awareness Drive'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 50,
            'attendance_range'                 => '40-100',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['15-25'],
            'event_coordinator_name'           => 'Test Coordinator',
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => md5('test-stuck-event-' . uniqid()),
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'pending',
            'sync_attempts'                    => 0,
        ]);

        // Override created_at to simulate the event being stuck
        $event->created_at = now()->subMinutes($createdMinutesAgo);
        $event->saveQuietly();

        return $event;
    }
}

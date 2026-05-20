<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FIX-DATA-01: Hash corruption audit command tests.
 *
 * Verifies that audit:rehash-events correctly:
 *  1. Detects events where event_coordinator_name is empty (B-03 bug state)
 *  2. Sets hash_was_corrupted = true on affected records
 *  3. Does NOT modify events with a correct, non-empty coordinator name
 *  4. Writes a report file to storage/audit/
 *  5. Respects --dry-run: reports findings without writing to DB
 *  6. Returns non-zero exit code when corruption is found
 */
class AuditRehashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure audit directory exists for report writing
        if (!is_dir(storage_path('audit'))) {
            mkdir(storage_path('audit'), 0755, true);
        }
    }

    /** @test */
    public function detects_event_with_empty_coordinator_as_corrupted(): void
    {
        // Simulate a B-03 corrupted event: coordinator_name is empty in the DB
        $corruptEvent = $this->createEvent(['event_coordinator_name' => '']);

        $this->artisan('audit:rehash-events')
            ->assertExitCode(1); // Non-zero = corruption found

        $corruptEvent->refresh();
        $this->assertTrue(
            (bool) $corruptEvent->hash_was_corrupted,
            'Event with empty coordinator_name must be flagged as hash_was_corrupted'
        );
    }

    /** @test */
    public function does_not_flag_event_with_correct_coordinator_name(): void
    {
        // A clean event with all fields populated
        $cleanEvent = $this->createEvent(['event_coordinator_name' => 'Mohammad Ashraf']);

        $this->artisan('audit:rehash-events')
            ->assertExitCode(0); // Zero = no corruption found

        $cleanEvent->refresh();
        $this->assertFalse(
            (bool) $cleanEvent->hash_was_corrupted,
            'Event with valid coordinator_name must NOT be flagged'
        );
    }

    /** @test */
    public function detects_semantic_hash_mismatch_as_corrupted(): void
    {
        // Create an event with a correct coordinator but a WRONG semantic_hash
        // (simulating B-03: hash was computed with empty coordinator)
        $event = $this->createEvent(['event_coordinator_name' => 'Valid Coordinator']);

        // Overwrite the semantic_hash with one computed using empty coordinator
        $wrongHash = md5(
            strtolower(trim($event->event_name)) . '|' .
            strtolower(trim((string) $event->event_date)) . '|' .
            strtolower(trim($event->event_venue)) . '|' .
            $event->actual_attendance . '|' .
            $event->block_id . '|' .
            '' // empty coordinator — the B-03 bug
        );

        DB::table('events')
            ->where('id', $event->id)
            ->update(['semantic_hash' => $wrongHash]);

        $this->artisan('audit:rehash-events')
            ->assertExitCode(1);

        $event->refresh();
        $this->assertTrue(
            (bool) $event->hash_was_corrupted,
            'Event with mismatched semantic_hash must be flagged as corrupted'
        );
    }

    /** @test */
    public function dry_run_does_not_write_corrupted_flag_to_db(): void
    {
        $corruptEvent = $this->createEvent(['event_coordinator_name' => '']);

        $this->artisan('audit:rehash-events', ['--dry-run' => true])
            ->assertExitCode(1) // Still reports corruption
            ->expectsOutputToContain('dry-run');

        $corruptEvent->refresh();
        $this->assertFalse(
            (bool) $corruptEvent->hash_was_corrupted,
            '--dry-run must not write hash_was_corrupted = true to DB'
        );
    }

    /** @test */
    public function command_writes_audit_report_file(): void
    {
        $this->createEvent(['event_coordinator_name' => '']);

        $this->artisan('audit:rehash-events');

        $pattern = storage_path('audit/hash-audit-' . now()->format('Y-m-d') . '.log');
        $this->assertFileExists($pattern, 'Audit report file must be created in storage/audit/');

        $contents = file_get_contents($pattern);
        $this->assertStringContainsString('SUMMARY', $contents);
        $this->assertStringContainsString('Corrupted events found', $contents);
    }

    /** @test */
    public function exit_code_zero_when_no_corruption_detected(): void
    {
        // All events are clean
        $this->createEvent(['event_name' => 'Clean Event A', 'event_coordinator_name' => 'Valid Coordinator One']);
        $this->createEvent(['event_name' => 'Clean Event B', 'event_coordinator_name' => 'Valid Coordinator Two']);

        $this->artisan('audit:rehash-events')
            ->assertExitCode(0);
    }

    /** @test */
    public function counts_multiple_corrupted_events_correctly(): void
    {
        $this->createEvent(['event_name' => 'Corrupt Event A', 'event_coordinator_name' => '']);
        $this->createEvent(['event_name' => 'Corrupt Event B', 'event_coordinator_name' => '']);
        $this->createEvent(['event_name' => 'Valid Event C', 'event_coordinator_name' => 'Valid Coordinator']);

        $this->artisan('audit:rehash-events')
            ->assertExitCode(1)
            ->expectsOutputToContain('2'); // 2 corrupted events

        $this->assertEquals(2, Event::where('hash_was_corrupted', true)->count());
        $this->assertEquals(1, Event::where('hash_was_corrupted', false)->count());
    }

    /**
     * Helper: create a minimal valid event record.
     */
    private function createEvent(array $overrides = []): Event
    {
        $block = Block::first() ?? Block::create([
            'id'          => 3,
            'name'        => 'Audit Test Block',
            'slug'        => 'audit-test-block',
            'district_id' => 5,
        ]);

        $eventName       = $overrides['event_name'] ?? 'Audit Test Event';
        $coordinatorName = $overrides['event_coordinator_name'] ?? 'Default Coordinator';

        $semanticHash = \App\Models\Event::generateSemanticHash(
            $eventName,
            '2026-05-01',
            'Test Venue',
            50,
            $block->id,
            $coordinatorName
        );

        return Event::create(array_merge([
            'event_name'                       => 'Audit Test Event',
            'event_date'                       => '2026-05-01',
            'event_venue'                      => 'Test Venue',
            'event_category'                   => ['Awareness Drive'],
            'district_name'                    => 'Budgam',
            'block_id'                         => $block->id,
            'actual_attendance'                => 50,
            'attendance_range'                 => '40-100',
            'target_audience'                  => ['Students'],
            'age_group'                        => ['15-25'],
            'event_coordinator_name'           => $coordinatorName,
            'event_coordinator_contact_number' => '9876543210',
            'event_coordinator_desig'          => 'Teacher',
            'photo_paths'                      => [],
            'unique_hash'                      => md5(uniqid('', true)),
            'semantic_hash'                    => $semanticHash,
            'submission_id'                    => md5(uniqid('', true)),
            'sync_status'                      => 'synced',
            'sync_attempts'                    => 1,
        ], $overrides));
    }
}

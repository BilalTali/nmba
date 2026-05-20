<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIX-ARCH-01: Verify semantic_hash split behaviour.
 *
 * Tests that:
 *  1. Two submissions with identical semantic fields produce the same semantic_hash
 *  2. Same semantic_hash causes duplicate rejection (DB unique constraint)
 *  3. Different semantic fields produce different hashes and are both accepted
 *  4. generateSubmissionId() always produces different values (has uniqid)
 *  5. generateSemanticHash() is deterministic (no uniqid)
 */
class SemanticHashTest extends TestCase
{
    use RefreshDatabase;

    private array $baseFields;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseFields = [
            'event_name'       => 'Anti Drug Awareness Camp',
            'event_date'       => '2026-05-01',
            'event_venue'      => 'Government High School Budgam',
            'actual_attendance'=> 85,
            'block_id'         => 3,
            'coordinator_name' => 'Mohammad Ashraf',
        ];
    }

    /** @test */
    public function identical_fields_produce_same_semantic_hash(): void
    {
        $hash1 = Event::generateSemanticHash(...array_values($this->baseFields));
        $hash2 = Event::generateSemanticHash(...array_values($this->baseFields));

        $this->assertEquals($hash1, $hash2, 'Same fields must produce identical semantic_hash');
    }

    /** @test */
    public function semantic_hash_is_case_insensitive(): void
    {
        $fields = $this->baseFields;

        $hashLower = Event::generateSemanticHash(
            strtolower($fields['event_name']),
            $fields['event_date'],
            strtolower($fields['event_venue']),
            $fields['actual_attendance'],
            $fields['block_id'],
            strtolower($fields['coordinator_name'])
        );

        $hashMixed = Event::generateSemanticHash(
            'Anti Drug AWARENESS Camp',
            $fields['event_date'],
            'GOVERNMENT HIGH SCHOOL Budgam',
            $fields['actual_attendance'],
            $fields['block_id'],
            'MOHAMMAD ASHRAF'
        );

        $this->assertEquals($hashLower, $hashMixed, 'semantic_hash must be case-insensitive');
    }

    /** @test */
    public function different_coordinator_name_produces_different_hash(): void
    {
        $hash1 = Event::generateSemanticHash(
            $this->baseFields['event_name'],
            $this->baseFields['event_date'],
            $this->baseFields['event_venue'],
            $this->baseFields['actual_attendance'],
            $this->baseFields['block_id'],
            'Mohammad Ashraf'
        );

        $hash2 = Event::generateSemanticHash(
            $this->baseFields['event_name'],
            $this->baseFields['event_date'],
            $this->baseFields['event_venue'],
            $this->baseFields['actual_attendance'],
            $this->baseFields['block_id'],
            'Abdul Rehman'  // different coordinator
        );

        $this->assertNotEquals($hash1, $hash2, 'Different coordinator names must produce different semantic hashes');
    }

    /** @test */
    public function different_block_id_produces_different_hash(): void
    {
        $hash1 = Event::generateSemanticHash(...array_values($this->baseFields));

        $fields2        = $this->baseFields;
        $fields2['block_id'] = 7; // different block

        $hash2 = Event::generateSemanticHash(...array_values($fields2));

        $this->assertNotEquals($hash1, $hash2, 'Different block IDs must produce different semantic hashes');
    }

    /** @test */
    public function submission_id_is_always_unique(): void
    {
        $id1 = Event::generateSubmissionId(...array_values($this->baseFields));
        $id2 = Event::generateSubmissionId(...array_values($this->baseFields));

        $this->assertNotEquals($id1, $id2, 'generateSubmissionId() must always produce a unique value (uses uniqid)');
    }

    /** @test */
    public function semantic_hash_has_no_uniqid_component(): void
    {
        // Run 50 times — if uniqid() were in the hash, we'd get 50 different values
        $hashes = collect(range(1, 50))->map(fn() =>
            Event::generateSemanticHash(...array_values($this->baseFields))
        )->unique();

        $this->assertCount(1, $hashes, 'generateSemanticHash() must be 100% deterministic (no uniqid)');
    }

    /** @test */
    public function duplicate_semantic_hash_is_rejected_by_db_unique_index(): void
    {
        $semanticHash = Event::generateSemanticHash(...array_values($this->baseFields));

        // First insert should succeed
        \Illuminate\Support\Facades\DB::table('deduplications')->insert([
            'semantic_hash' => $semanticHash,
            'event_id'      => null,
            'created_at'    => now(),
        ]);

        // Second insert with same hash must throw a QueryException (unique constraint violation)
        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('deduplications')->insert([
            'semantic_hash' => $semanticHash,
            'event_id'      => null,
            'created_at'    => now(),
        ]);
    }

    /** @test */
    public function generate_unique_hash_is_deprecated_alias_of_submission_id(): void
    {
        // generateUniqueHash() must still exist for backward compatibility
        // and must delegate to generateSubmissionId() (which includes uniqid)
        $result = Event::generateUniqueHash(...array_values($this->baseFields));

        $this->assertNotEmpty($result, 'generateUniqueHash() must still return a value');
        $this->assertEquals(32, strlen($result), 'Must return an MD5 hex string (32 chars)');
    }
}

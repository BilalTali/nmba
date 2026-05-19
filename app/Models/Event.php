<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $event_name
 * @property \Illuminate\Support\Carbon $event_date
 * @property string $event_venue
 * @property array $event_category
 * @property int|null $district_id
 * @property string $district_name
 * @property int $block_id
 * @property string|null $ward
 * @property string|null $village
 * @property string $attendance_range
 * @property int $actual_attendance
 * @property array $target_audience
 * @property array $age_group
 * @property string $event_coordinator_name
 * @property string $event_coordinator_contact_number
 * @property string $event_coordinator_desig
 * @property array $photo_paths
 * @property string $unique_hash
 * @property string $sync_status
 * @property int $sync_attempts
 * @property \Illuminate\Support\Carbon|null $last_attempt_at
 * @property string|null $last_error_log
 */
class Event extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'event_name',
        'event_date',
        'event_venue',
        'event_category',
        'event_category_remark',
        'district_id',
        'district_name',
        'block_id',
        'ward',
        'village',
        'attendance_range',
        'actual_attendance',
        'target_audience',
        'age_group',
        'event_coordinator_name',
        'event_coordinator_contact_number',
        'event_coordinator_desig',
        'photo_paths',
        'unique_hash',
        'sync_status',
        'sync_attempts',
        'last_attempt_at',
        'last_error_log',
    ];

    protected $casts = [
        'event_date'       => 'date',
        'event_category'   => 'array',
        'target_audience'  => 'array',
        'age_group'        => 'array',
        'photo_paths'      => 'array',
        'actual_attendance'=> 'integer',
        'block_id'         => 'integer',
        'district_id'      => 'integer',
        'sync_attempts'    => 'integer',
        'last_attempt_at'  => 'datetime',
    ];

    /**
     * Query scope: only pending synchronization records.
     */
    public function scopePending($query)
    {
        return $query->where('sync_status', 'pending');
    }

    /**
     * Query scope: only permanently failed records.
     */
    public function scopeFailedPermanently($query)
    {
        return $query->where('sync_status', 'failed_permanently');
    }

    /**
     * Compute a collision-resistant, case-insensitive idempotency token.
     * Incorporates block_id to prevent global collisions when multiple schools
     * host overlapping events on the same date with the same headcount.
     */
    public static function generateUniqueHash(
        string $name,
        string $date,
        string $venue,
        int $attendance,
        int $blockId
    ): string {
        return md5(
            strtolower(trim($name)) . '|' .
            strtolower(trim($date)) . '|' .
            strtolower(trim($venue)) . '|' .
            $attendance . '|' .
            $blockId
        );
    }

    /**
     * Centralized single-point attendance range inference.
     * Used by both the form request (prepareForValidation) and the portal payload mapper.
     *
     * @throws \InvalidArgumentException If attendance is zero or negative.
     */
    public static function inferAttendanceRange(int $count): string
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException(
                'Actual attendance headcount must be a positive non-zero integer.'
            );
        }

        return match (true) {
            $count <= 40  => '20-40',
            $count <= 100 => '40-100',
            $count <= 150 => '100-150',
            $count <= 200 => '150-200',
            $count <= 500 => '200-500',
            default       => '500 & above',
        };
    }

    /**
     * Mark record as successfully synced and clear any previous error log.
     */
    public function markSynced(): void
    {
        $this->update([
            'sync_status'    => 'synced',
            'last_attempt_at'=> now(),
            'last_error_log' => null,
        ]);
    }

    /**
     * Mark record as failed (retriable) and store the error message.
     * Resets status back to 'pending' so the scheduler can re-queue it.
     * Truncates error to 5000 chars to prevent DB storage bloat.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'sync_status'    => 'pending',
            'last_attempt_at'=> now(),
            'last_error_log' => mb_substr($error, 0, 5000),
        ]);
    }

    /**
     * Mark record as permanently failed (dead-lettered).
     * Truncates error to 5000 chars to prevent DB storage bloat.
     */
    public function markFailedPermanently(string $error): void
    {
        $this->update([
            'sync_status'    => 'failed_permanently',
            'last_attempt_at'=> now(),
            'last_error_log' => '[CRITICAL PERMANENT UNRECOVERABLE]: ' . mb_substr($error, 0, 5000),
        ]);
    }
}

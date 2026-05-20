<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FIX-OPS-02: Per-attempt sync audit log entry.
 *
 * @property int $id
 * @property int $event_id
 * @property \Illuminate\Support\Carbon $attempted_at
 * @property int $attempt_number
 * @property int|null $http_status_code
 * @property string|null $api_response_snippet
 * @property string $outcome  — 'success' | 'failure' | 'permanent_failure'
 * @property int|null $worker_pid
 */
class EventSyncLog extends Model
{
    public $timestamps = false; // table uses attempted_at, not created_at/updated_at

    protected $fillable = [
        'event_id',
        'attempted_at',
        'attempt_number',
        'http_status_code',
        'api_response_snippet',
        'outcome',
        'worker_pid',
    ];

    protected $casts = [
        'attempted_at'    => 'datetime',
        'attempt_number'  => 'integer',
        'http_status_code'=> 'integer',
        'worker_pid'      => 'integer',
    ];

    /**
     * The event this log entry belongs to.
     */
    public function event(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

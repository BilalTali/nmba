<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemTelemetry extends Model
{
    public $timestamps = false;

    protected $table = 'system_telemetries';

    protected $fillable = [
        'created_at',
        'cpu_load',
        'memory_usage',
        'disk_usage',
        'pending_jobs',
        'response_time',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'cpu_load' => 'float',
        'memory_usage' => 'float',
        'disk_usage' => 'float',
        'pending_jobs' => 'integer',
        'response_time' => 'float',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedTelemetry extends Model
{
    protected $fillable = [
        'supplier',
        'event_id',
        'type',
        'device_id',
        'occurred_at',
        'payload',
        'status'
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime'
    ];
}
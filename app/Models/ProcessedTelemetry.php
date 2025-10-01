<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessedTelemetry extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime'
    ];
}

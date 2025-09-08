<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ProcessedTelemetry extends Model
{
    protected $guarded = ['id'];


    protected $casts = [
        'payload'      => 'array',
        'occurred_at'  => 'datetime',
        'forwarded_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    public function scopeForSupplier(Builder $query, string $supplier): Builder
    {
        return $query->where('supplier', $supplier);
    }

    public function scopeForDevice(Builder $query, string $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function markForwarded(): void
    {
        $this->update([
            'status'       => 'forwarded',
            'forwarded_at' => now(),
        ]);
    }

    public function markFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
        ]);
    }

    public function isForwarded(): bool
    {
        return $this->status === 'forwarded';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}

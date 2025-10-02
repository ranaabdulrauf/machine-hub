<?php

namespace App\Models;

use App\Enums\TelemetryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class ProcessedTelemetry extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'forwarded_at' => 'datetime',
        'status' => TelemetryStatus::class
    ];

    protected $fillable = [
        'supplier',
        'event_id',
        'type',
        'occurred_at',
        'payload',
        'status',
        'forwarded_at'
    ];

    /**
     * Validation rules
     */
    public static function rules(): array
    {
        return [
            'supplier' => 'required|string|max:50',
            'event_id' => 'required|string',
            'type' => 'nullable|string',
            'occurred_at' => 'nullable|date',
            'payload' => 'required|array',
            'status' => 'required|string|in:' . implode(',', TelemetryStatus::values()),
            'forwarded_at' => 'nullable|date'
        ];
    }

    /**
     * Mark telemetry as pending
     */
    public function markAsPending(): bool
    {
        return $this->updateStatus(TelemetryStatus::PENDING);
    }

    /**
     * Mark telemetry as processing
     */
    public function markAsProcessing(): bool
    {
        return $this->updateStatus(TelemetryStatus::PROCESSING);
    }

    /**
     * Mark telemetry as forwarded successfully
     */
    public function markAsForwarded(): bool
    {
        $result = $this->updateStatus(TelemetryStatus::FORWARDED);

        if ($result) {
            $this->update(['forwarded_at' => now()]);
        }

        return $result;
    }

    /**
     * Mark telemetry as failed (permanent failure - no retry)
     * Use for: configuration errors, validation errors, authentication failures
     */
    public function markAsFailed(string $reason = null): bool
    {
        $result = $this->updateStatus(TelemetryStatus::FAILED);

        if ($result && $reason) {
            Log::warning("[ProcessedTelemetry] Telemetry marked as failed (permanent)", [
                'id' => $this->id,
                'supplier' => $this->supplier,
                'event_id' => $this->event_id,
                'reason' => $reason,
                'note' => 'This is a permanent failure - no retry will be attempted'
            ]);
        }

        return $result;
    }

    /**
     * Mark telemetry as error (temporary failure - will retry)
     * Use for: network timeouts, server errors, connection issues
     */
    public function markAsError(string $reason = null): bool
    {
        $result = $this->updateStatus(TelemetryStatus::ERROR);

        if ($result && $reason) {
            Log::error("[ProcessedTelemetry] Telemetry marked as error (temporary)", [
                'id' => $this->id,
                'supplier' => $this->supplier,
                'event_id' => $this->event_id,
                'reason' => $reason,
                'note' => 'This is a temporary error - retry will be attempted'
            ]);
        }

        return $result;
    }

    /**
     * Update status with logging
     */
    protected function updateStatus(TelemetryStatus $status): bool
    {
        $oldStatus = $this->status;
        $result = $this->update(['status' => $status]);

        if ($result) {
            Log::info("[ProcessedTelemetry] Status updated", [
                'id' => $this->id,
                'supplier' => $this->supplier,
                'event_id' => $this->event_id,
                'old_status' => $oldStatus?->value,
                'new_status' => $status->value,
                'status_label' => $status->label()
            ]);
        }

        return $result;
    }

    /**
     * Check if telemetry is in a specific status
     */
    public function hasStatus(TelemetryStatus $status): bool
    {
        return $this->status === $status;
    }

    /**
     * Check if telemetry is completed (success or failure)
     */
    public function isCompleted(): bool
    {
        return $this->status?->isCompleted() ?? false;
    }

    /**
     * Check if telemetry was successful
     */
    public function isSuccessful(): bool
    {
        return $this->status?->isSuccess() ?? false;
    }

    /**
     * Check if telemetry failed
     */
    public function hasFailed(): bool
    {
        return $this->status?->isFailure() ?? false;
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? 'Unknown';
    }
}

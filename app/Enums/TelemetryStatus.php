<?php

namespace App\Enums;

enum TelemetryStatus: string
{
    case PENDING = 'pending';      // Telemetry created, waiting to be processed
    case PROCESSING = 'processing'; // Currently being forwarded to webhook
    case FORWARDED = 'forwarded';   // Successfully sent to webhook endpoint
    case FAILED = 'failed';         // Permanent failure - no retry (config errors, validation errors)
    case ERROR = 'error';           // Temporary failure - will retry (network issues, server errors)

    /**
     * Get all status values as array
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human readable label for status
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PROCESSING => 'Processing',
            self::FORWARDED => 'Forwarded',
            self::FAILED => 'Failed',
            self::ERROR => 'Error',
        };
    }

    /**
     * Check if status indicates completion (success or failure)
     */
    public function isCompleted(): bool
    {
        return in_array($this, [self::FORWARDED, self::FAILED, self::ERROR]);
    }

    /**
     * Check if status indicates success
     */
    public function isSuccess(): bool
    {
        return $this === self::FORWARDED;
    }

    /**
     * Check if status indicates failure
     */
    public function isFailure(): bool
    {
        return in_array($this, [self::FAILED, self::ERROR]);
    }

    /**
     * Check if status should trigger retry
     */
    public function shouldRetry(): bool
    {
        return $this === self::ERROR;
    }

    /**
     * Check if status is permanent (no retry)
     */
    public function isPermanent(): bool
    {
        return $this === self::FAILED;
    }
}

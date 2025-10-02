<?php

namespace App\Jobs;

use App\DTOs\TelemetryDTO;
use App\Models\ProcessedTelemetry;
use App\Tenants\TenantForwarder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ForwardTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $supplier;
    public string $tenant;
    public TelemetryDTO $dto;
    protected int $maxTries = 3;
    protected int $timeout = 30;

    protected ?ProcessedTelemetry $telemetryRecord = null;

    public function __construct(string $supplier, string $tenant, TelemetryDTO $dto)
    {
        $this->supplier = $supplier;
        $this->tenant = $tenant;
        $this->dto = $dto;
    }

    public function handle(TenantForwarder $tenantForwarder): void
    {
        // Find the ProcessedTelemetry record
        $this->telemetryRecord = ProcessedTelemetry::where('supplier', $this->supplier)
            ->where('event_id', $this->dto->eventId)
            ->first();

        Log::info("[ForwardTelemetryJob] Starting telemetry forwarding", [
            'supplier' => $this->supplier,
            'tenant' => $this->tenant,
            'event_id' => $this->dto->eventId,
            'attempt' => $this->attempts(),
            'environment' => app()->environment(),
            'telemetry_record_id' => $this->telemetryRecord?->id
        ]);

        // In development mode, always log the DTO data regardless of endpoint configuration
        if (app()->environment('local', 'development', 'dev')) {
            Log::info("[ForwardTelemetryJob] Development mode - logging telemetry data", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'dto_data' => $this->dto->toArray(),
                'message' => 'Job completed successfully in development mode - data logged for inspection'
            ]);

            // Mark as forwarded in development mode since we're logging the data
            $this->telemetryRecord?->markAsForwarded();
            return; // Exit early in development mode
        }

        // Production mode: attempt to forward with comprehensive error handling
        try {
            $tenantForwarder->forwardToTenant($this->dto, $this->supplier, $this->tenant);

            // If we get here, forwarding was successful
            $this->telemetryRecord?->markAsForwarded();

            Log::info("[ForwardTelemetryJob] Telemetry forwarded successfully", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'location' => 'ForwardTelemetryJob::handle() - successful forwarding'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Mark as failed - validation errors should not retry
            $this->telemetryRecord?->markAsFailed("Validation error: " . $e->getMessage());

            Log::error("[ForwardTelemetryJob] Validation error - configuration or webhook issue", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'dto_data' => $this->dto->toArray(),
                'error_type' => 'validation_error',
                'error_message' => $e->getMessage(),
                'validation_errors' => $e->errors(),
                'location' => 'ForwardTelemetryJob::handle() - validation failed',
                'solution' => 'Check webhook configuration and data format requirements'
            ]);

            throw $e;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Mark as error for HTTP client errors (these will retry)
            $this->telemetryRecord?->markAsError("HTTP client error: " . $e->getMessage());

            Log::error("[ForwardTelemetryJob] HTTP client error during forwarding", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'dto_data' => $this->dto->toArray(),
                'error_type' => 'http_client_error',
                'error_message' => $e->getMessage(),
                'response_status' => $e->response?->status(),
                'response_body' => $e->response?->body(),
                'location' => 'ForwardTelemetryJob::handle() - HTTP request failed',
                'solution' => 'Check webhook URL accessibility and network connectivity'
            ]);

            throw $e;
        } catch (\Exception $e) {
            // Mark as error for general exceptions (these will retry)
            $this->telemetryRecord?->markAsError("General exception: " . $e->getMessage());

            Log::error("[ForwardTelemetryJob] General exception during telemetry forwarding", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'dto_data' => $this->dto->toArray(),
                'error_type' => 'general_exception',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'location' => 'ForwardTelemetryJob::handle() - unexpected error',
                'solution' => 'Check logs for detailed error information'
            ]);

            throw $e;
        } catch (\Throwable $e) {
            // Mark as error for fatal errors (these will retry)
            $this->telemetryRecord?->markAsError("Fatal error: " . $e->getMessage());

            Log::error("[ForwardTelemetryJob] Fatal error during telemetry forwarding", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'dto_data' => $this->dto->toArray(),
                'error_type' => 'fatal_error',
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'location' => 'ForwardTelemetryJob::handle() - fatal error',
                'solution' => 'Check system logs and contact support'
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Mark as failed when job fails permanently
        $this->telemetryRecord?->markAsFailed("Job failed permanently after {$this->attempts()} attempts: " . $exception->getMessage());

        Log::error("[ForwardTelemetryJob] Job failed permanently", [
            'supplier' => $this->supplier,
            'tenant' => $this->tenant,
            'event_id' => $this->dto->eventId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'telemetry_record_id' => $this->telemetryRecord?->id
        ]);
    }
}

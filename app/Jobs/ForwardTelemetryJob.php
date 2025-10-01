<?php

namespace App\Jobs;

use App\DTOs\TelemetryDTO;
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

    public function __construct(string $supplier, string $tenant, TelemetryDTO $dto)
    {
        $this->supplier = $supplier;
        $this->tenant = $tenant;
        $this->dto = $dto;
    }

    public function handle(TenantForwarder $tenantForwarder): void
    {
        Log::info("[ForwardTelemetryJob] Starting telemetry forwarding", [
            'supplier' => $this->supplier,
            'tenant' => $this->tenant,
            'event_id' => $this->dto->eventId,
            'attempt' => $this->attempts()
        ]);

        try {
            $success = $tenantForwarder->forwardToTenant($this->dto, $this->supplier, $this->tenant);

            if ($success) {
                Log::info("[ForwardTelemetryJob] Telemetry forwarded successfully", [
                    'supplier' => $this->supplier,
                    'tenant' => $this->tenant,
                    'event_id' => $this->dto->eventId
                ]);
            } else {
                Log::error("[ForwardTelemetryJob] Failed to forward telemetry", [
                    'supplier' => $this->supplier,
                    'tenant' => $this->tenant,
                    'event_id' => $this->dto->eventId
                ]);

                // This will trigger a retry
                throw new \Exception("Failed to forward telemetry to tenant");
            }
        } catch (\Throwable $e) {
            Log::error("[ForwardTelemetryJob] Exception during telemetry forwarding", [
                'supplier' => $this->supplier,
                'tenant' => $this->tenant,
                'event_id' => $this->dto->eventId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("[ForwardTelemetryJob] Job failed permanently", [
            'supplier' => $this->supplier,
            'tenant' => $this->tenant,
            'event_id' => $this->dto->eventId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}

<?php

namespace App\Jobs;

use App\Models\ProcessedTelemetry;
use App\Tenants\TenantForwarder;
use App\DTOs\TelemetryDTO;
use App\Jobs\ForwardTelemetryJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ForwardApiSupplierTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $supplier;

    public function __construct(string $supplier)
    {
        $this->supplier = $supplier;
    }

    public function handle(TenantForwarder $tenantForwarder): void
    {
        Log::info("[ForwardApiSupplierTelemetryJob] Starting telemetry forwarding", [
            'supplier' => $this->supplier
        ]);

        try {
            // Get all pending telemetries for this supplier
            $pendingTelemetries = ProcessedTelemetry::where('supplier', $this->supplier)
                ->where('status', 'pending')
                ->get();

            if ($pendingTelemetries->isEmpty()) {
                Log::info("[ForwardApiSupplierTelemetryJob] No pending telemetries found", [
                    'supplier' => $this->supplier
                ]);
                return;
            }

            // Get all configured tenants for this supplier
            $tenants = $tenantForwarder->getConfiguredTenants($this->supplier);

            if (empty($tenants)) {
                Log::warning("[ForwardApiSupplierTelemetryJob] No tenants configured", [
                    'supplier' => $this->supplier
                ]);
                return;
            }

            $processedCount = 0;

            foreach ($pendingTelemetries as $telemetry) {
                try {
                    // Convert to DTO
                    $dto = new TelemetryDTO(
                        type: $telemetry->type,
                        eventId: $telemetry->event_id,
                        deviceId: $telemetry->device_id,
                        occurredAt: $telemetry->occurred_at,
                        payload: $telemetry->payload
                    );

                    // Dispatch forwarding jobs for each tenant
                    foreach ($tenants as $tenant) {
                        ForwardTelemetryJob::dispatch($this->supplier, $tenant, $dto);
                    }

                    // Mark as processing (will be updated by the ForwardTelemetryJob)
                    $telemetry->update(['status' => 'processing']);
                    $processedCount++;

                    Log::info("[ForwardApiSupplierTelemetryJob] Dispatched forwarding jobs", [
                        'supplier' => $this->supplier,
                        'event_id' => $telemetry->event_id,
                        'tenants' => $tenants
                    ]);
                } catch (\Throwable $e) {
                    $telemetry->update(['status' => 'error']);

                    Log::error("[ForwardApiSupplierTelemetryJob] Error processing telemetry", [
                        'supplier' => $this->supplier,
                        'event_id' => $telemetry->event_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info("[ForwardApiSupplierTelemetryJob] Telemetry forwarding completed", [
                'supplier' => $this->supplier,
                'processed_count' => $processedCount,
                'total_events' => $pendingTelemetries->count()
            ]);
        } catch (\Throwable $e) {
            Log::error("[ForwardApiSupplierTelemetryJob] Exception during telemetry forwarding", [
                'supplier' => $this->supplier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

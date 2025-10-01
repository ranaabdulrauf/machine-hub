<?php

namespace App\Jobs;

use App\Suppliers\SupplierRegistry;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ForwardAllApiSuppliersTelemetryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info("[ForwardAllApiSuppliersTelemetryJob] Starting forwarding for all API suppliers");

        try {
            // Get all API suppliers from registry
            $apiSuppliers = SupplierRegistry::getApiSuppliers();

            if (empty($apiSuppliers)) {
                Log::info("[ForwardAllApiSuppliersTelemetryJob] No API suppliers found");
                return;
            }

            Log::info("[ForwardAllApiSuppliersTelemetryJob] Found API suppliers", [
                'suppliers' => $apiSuppliers
            ]);

            // Dispatch forwarding jobs for each API supplier
            foreach ($apiSuppliers as $supplier) {
                ForwardApiSupplierTelemetryJob::dispatch($supplier);
                
                Log::info("[ForwardAllApiSuppliersTelemetryJob] Dispatched forwarding job", [
                    'supplier' => $supplier
                ]);
            }

            Log::info("[ForwardAllApiSuppliersTelemetryJob] All API supplier forwarding jobs dispatched", [
                'count' => count($apiSuppliers)
            ]);

        } catch (\Throwable $e) {
            Log::error("[ForwardAllApiSuppliersTelemetryJob] Exception during processing", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

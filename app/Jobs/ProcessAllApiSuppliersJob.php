<?php

namespace App\Jobs;

use App\Suppliers\SupplierRegistry;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class ProcessAllApiSuppliersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info("[ProcessAllApiSuppliersJob] Starting processing for all API suppliers");

        try {
            // Get all API suppliers from registry
            $apiSuppliers = SupplierRegistry::getApiSuppliers();

            if (empty($apiSuppliers)) {
                Log::info("[ProcessAllApiSuppliersJob] No API suppliers found");
                return;
            }

            Log::info("[ProcessAllApiSuppliersJob] Found API suppliers", [
                'suppliers' => $apiSuppliers
            ]);

            // Dispatch fetch jobs for each API supplier
            foreach ($apiSuppliers as $supplier) {
                FetchApiSupplierDataJob::dispatch($supplier);
                
                Log::info("[ProcessAllApiSuppliersJob] Dispatched fetch job", [
                    'supplier' => $supplier
                ]);
            }

            Log::info("[ProcessAllApiSuppliersJob] All API supplier jobs dispatched", [
                'count' => count($apiSuppliers)
            ]);

        } catch (\Throwable $e) {
            Log::error("[ProcessAllApiSuppliersJob] Exception during processing", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

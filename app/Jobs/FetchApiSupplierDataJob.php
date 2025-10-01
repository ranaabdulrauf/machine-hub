<?php

namespace App\Jobs;

use App\Suppliers\AbstractSupplierAdapter;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class FetchApiSupplierDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $supplier;

    public function __construct(string $supplier)
    {
        $this->supplier = $supplier;
    }

    public function handle(): void
    {
        Log::info("[FetchApiSupplierDataJob] Starting API data fetch", [
            'supplier' => $this->supplier
        ]);

        try {
            // Get supplier adapter from registry
            $adapter = $this->getSupplierAdapter($this->supplier);

            if (!$adapter) {
                Log::error("[FetchApiSupplierDataJob] Supplier adapter not found", [
                    'supplier' => $this->supplier
                ]);
                return;
            }

            // Verify it's an API supplier
            if ($adapter->mode() !== 'api') {
                Log::warning("[FetchApiSupplierDataJob] Supplier is not API mode", [
                    'supplier' => $this->supplier,
                    'mode' => $adapter->mode()
                ]);
                return;
            }

            // Call the adapter's API method
            $adapter->handleApi();

            Log::info("[FetchApiSupplierDataJob] API data fetch completed successfully", [
                'supplier' => $this->supplier
            ]);
        } catch (\Throwable $e) {
            Log::error("[FetchApiSupplierDataJob] API data fetch failed", [
                'supplier' => $this->supplier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function getSupplierAdapter(string $supplier): ?AbstractSupplierAdapter
    {
        $adapterClass = "App\\Suppliers\\" . ucfirst($supplier) . "Adapter";

        if (!class_exists($adapterClass)) {
            return null;
        }

        return new $adapterClass();
    }
}

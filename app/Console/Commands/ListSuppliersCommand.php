<?php

namespace App\Console\Commands;

use App\Suppliers\SupplierRegistry;
use Illuminate\Console\Command;

class ListSuppliersCommand extends Command
{
    protected $signature = 'supplier:list';
    protected $description = 'List all registered suppliers';

    public function handle()
    {
        $this->info('Registered Suppliers:');
        $this->newLine();

        $suppliers = SupplierRegistry::all();
        $apiSuppliers = SupplierRegistry::getApiSuppliers();
        $webhookSuppliers = SupplierRegistry::getWebhookSuppliers();

        if (empty($suppliers)) {
            $this->warn('No suppliers found');
            return 0;
        }

        $headers = ['Supplier', 'Mode', 'Adapter Class', 'Status'];
        $rows = [];

        foreach ($suppliers as $supplier) {
            $adapterClass = SupplierRegistry::getAdapterClass($supplier);
            $mode = SupplierRegistry::getMode($supplier);
            $status = '✅ Active';

            $rows[] = [
                $supplier,
                $mode,
                $adapterClass,
                $status
            ];
        }

        $this->table($headers, $rows);

        $this->newLine();
        $this->info("Total: " . count($suppliers) . " suppliers");
        $this->info("Webhook: " . count($webhookSuppliers) . " suppliers");
        $this->info("API: " . count($apiSuppliers) . " suppliers");

        return 0;
    }
}

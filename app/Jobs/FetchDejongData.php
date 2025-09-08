<?php

namespace App\Jobs;

use App\Models\ProcessedTelemetry;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\MachineHub\Core\SupplierRegistry;
use Illuminate\Foundation\Queue\Queueable;
use App\MachineHub\Core\Traits\HasFetchLog;
use App\MachineHub\Suppliers\DejongAdapter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class FetchDejongData implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasFetchLog;

    public function handle(SupplierRegistry $registry): void
    {
        $suppliers = $registry->all();

        foreach ($suppliers as $adapter) {
            if ($adapter->mode() === 'api') {
                $adapter->handleApi(); // adapter takes care of dates + persistence
            }
        }
    }
}

<?php

namespace App\MachineHub\Core\Traits;

use Carbon\Carbon;
use App\Models\SupplierFetchLog;

trait HasFetchLog
{
    protected function getFetchRange(string $supplier, string $endpoint, ?int $fallbackMinutes = 60): array
    {
        $lastFetched = SupplierFetchLog::lastFetched($supplier, $endpoint);

        $start = $lastFetched
            ? $lastFetched->addSecond()
            : now()->subMinutes($fallbackMinutes);

        $end = now();

        return [$start, $end];
    }

    protected function markFetched(string $supplier, string $endpoint, Carbon $timestamp): void
    {
        SupplierFetchLog::updateFetched($supplier, $endpoint, $timestamp);
    }
}

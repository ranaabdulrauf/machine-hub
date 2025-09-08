<?php

namespace App\MachineHub\Core;

use App\MachineHub\Suppliers\WMFAdapter;
use App\MachineHub\Suppliers\DejongAdapter;
use App\MachineHub\Suppliers\AbstractSupplierAdapter;

class SupplierRegistry
{
    /** @var array<string, AbstractSupplierAdapter> */
    protected array $suppliers = [];

    public function __construct()
    {
        // You can bind adapters via service container or config
        $this->suppliers = [
            'dejong' => app(DejongAdapter::class),
            'wmf' => app(WMFAdapter::class),
            // 'other' => app(OtherAdapter::class),
        ];
    }

    public function all(): array
    {
        return $this->suppliers;
    }

    public function resolve(string $name): ?AbstractSupplierAdapter
    {
        return $this->suppliers[$name] ?? null;
    }
}

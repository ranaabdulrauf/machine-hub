<?php

namespace App\MachineHub\Core;

use App\MachineHub\Suppliers\WMFAdapter;
use App\MachineHub\Suppliers\SchaererAdapter;

class SupplierRegistry
{
    public static function resolve(string $supplier)
    {
        return match (strtolower($supplier)) {
            'wmf'      => new WMFAdapter(),
            default    => throw new \InvalidArgumentException("Unknown supplier: $supplier"),
        };
    }
}

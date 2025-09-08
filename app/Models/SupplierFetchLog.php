<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SupplierFetchLog extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['last_fetched_at' => 'datetime'];

    public static function lastFetched(string $supplier, string $endpoint): ?Carbon
    {
        return optional(
            static::where('supplier', $supplier)
                ->where('resource', $endpoint)
                ->first()
        )->last_fetched_at;
    }

    public static function updateFetched(string $supplier, string $endpoint, Carbon $timestamp): void
    {
        static::updateOrCreate(
            ['supplier' => $supplier, 'resource' => $endpoint],
            ['last_fetched_at' => $timestamp]
        );
    }
}

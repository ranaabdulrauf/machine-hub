<?php

namespace App\Suppliers;

use Illuminate\Support\Facades\Log;

class SupplierRegistry
{
    protected static array $suppliers = [
        'wmf' => \App\Suppliers\WMFAdapter::class,
        'dejong' => \App\Suppliers\DejongAdapter::class,
        'franke' => \App\Suppliers\FrankeAdapter::class,
    ];

    protected static bool $autoDiscovery = true;

    /**
     * Get all registered suppliers
     */
    public static function all(): array
    {
        if (self::$autoDiscovery) {
            self::discoverSuppliers();
        }
        
        return array_keys(self::$suppliers);
    }

    /**
     * Auto-discover suppliers from the Suppliers directory
     */
    protected static function discoverSuppliers(): void
    {
        $suppliersPath = app_path('Suppliers');
        
        if (!is_dir($suppliersPath)) {
            return;
        }

        $files = glob($suppliersPath . '/*Adapter.php');
        
        foreach ($files as $file) {
            $className = 'App\\Suppliers\\' . basename($file, '.php');
            
            if (class_exists($className)) {
                try {
                    $adapter = new $className();
                    $supplierName = $adapter->name();
                    
                    if (!isset(self::$suppliers[$supplierName])) {
                        self::$suppliers[$supplierName] = $className;
                        
                        Log::info("[SupplierRegistry] Auto-discovered supplier", [
                            'supplier' => $supplierName,
                            'adapter' => $className
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("[SupplierRegistry] Failed to discover supplier", [
                        'file' => $file,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Get all API suppliers
     */
    public static function getApiSuppliers(): array
    {
        $apiSuppliers = [];
        
        foreach (self::$suppliers as $name => $adapterClass) {
            if (class_exists($adapterClass)) {
                $adapter = new $adapterClass();
                if ($adapter->mode() === 'api') {
                    $apiSuppliers[] = $name;
                }
            }
        }
        
        return $apiSuppliers;
    }

    /**
     * Get all webhook suppliers
     */
    public static function getWebhookSuppliers(): array
    {
        $webhookSuppliers = [];
        
        foreach (self::$suppliers as $name => $adapterClass) {
            if (class_exists($adapterClass)) {
                $adapter = new $adapterClass();
                if ($adapter->mode() === 'webhook') {
                    $webhookSuppliers[] = $name;
                }
            }
        }
        
        return $webhookSuppliers;
    }

    /**
     * Register a new supplier
     */
    public static function register(string $name, string $adapterClass): void
    {
        self::$suppliers[$name] = $adapterClass;
        
        Log::info("[SupplierRegistry] Supplier registered", [
            'supplier' => $name,
            'adapter' => $adapterClass
        ]);
    }

    /**
     * Get supplier adapter class
     */
    public static function getAdapterClass(string $supplier): ?string
    {
        return self::$suppliers[$supplier] ?? null;
    }

    /**
     * Check if supplier is registered
     */
    public static function exists(string $supplier): bool
    {
        return isset(self::$suppliers[$supplier]);
    }

    /**
     * Get supplier mode
     */
    public static function getMode(string $supplier): ?string
    {
        $adapterClass = self::getAdapterClass($supplier);
        
        if (!$adapterClass || !class_exists($adapterClass)) {
            return null;
        }

        $adapter = new $adapterClass();
        return $adapter->mode();
    }
}

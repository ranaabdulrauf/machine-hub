<?php

namespace App\Tenants;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TenantResolver
{
    /**
     * Extract tenant from webhook URL path
     * Expected format: /webhook/{supplier}/{tenant}
     * Example: /webhook/wmf/yellowbeared
     */
    public static function fromWebhookPath(Request $request): ?string
    {
        $path = $request->path();
        $segments = explode('/', trim($path, '/'));
        
        // Expected: webhook/{supplier}/{tenant}
        if (count($segments) >= 3 && $segments[0] === 'webhook') {
            $tenant = $segments[2];
            Log::info("[TenantResolver] Resolved tenant from path", [
                'path' => $path,
                'tenant' => $tenant
            ]);
            return $tenant;
        }
        
        Log::warning("[TenantResolver] Could not resolve tenant from path", [
            'path' => $path,
            'segments' => $segments
        ]);
        
        return null;
    }
    
    /**
     * Extract tenant from query parameter
     * Example: /webhook/wmf?tenant=yellowbeared
     */
    public static function fromQueryParam(Request $request): ?string
    {
        $tenant = $request->query('tenant');
        
        if ($tenant) {
            Log::info("[TenantResolver] Resolved tenant from query param", [
                'tenant' => $tenant
            ]);
            return $tenant;
        }
        
        return null;
    }
    
    /**
     * Extract tenant from header
     * Example: X-Tenant: yellowbeared
     */
    public static function fromHeader(Request $request): ?string
    {
        $tenant = $request->header('X-Tenant');
        
        if ($tenant) {
            Log::info("[TenantResolver] Resolved tenant from header", [
                'tenant' => $tenant
            ]);
            return $tenant;
        }
        
        return null;
    }
    
    /**
     * Resolve tenant using multiple strategies
     */
    public static function resolve(Request $request): ?string
    {
        // Try different resolution strategies in order of preference
        $strategies = [
            'fromWebhookPath',
            'fromQueryParam', 
            'fromHeader'
        ];
        
        foreach ($strategies as $strategy) {
            $tenant = self::{$strategy}($request);
            if ($tenant) {
                return $tenant;
            }
        }
        
        return null;
    }
    
    /**
     * Get tenant configuration for a specific supplier
     */
    public static function getTenantConfig(string $supplier, string $tenant): ?array
    {
        $config = config("machinehub.suppliers.{$supplier}.tenants.{$tenant}");
        
        if (!$config) {
            Log::warning("[TenantResolver] No config found for tenant", [
                'supplier' => $supplier,
                'tenant' => $tenant
            ]);
        }
        
        return $config;
    }
}

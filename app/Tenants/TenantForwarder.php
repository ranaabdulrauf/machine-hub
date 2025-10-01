<?php

namespace App\Tenants;

use App\DTOs\TelemetryDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantForwarder
{
    /**
     * Forward telemetry to tenant-specific webhook
     */
    public function forwardToTenant(TelemetryDTO $dto, string $supplier, string $tenant): bool
    {
        $tenantConfig = TenantResolver::getTenantConfig($supplier, $tenant);
        
        if (!$tenantConfig) {
            Log::error("[TenantForwarder] No tenant config found", [
                'supplier' => $supplier,
                'tenant' => $tenant
            ]);
            return false;
        }
        
        $webhookUrl = $tenantConfig['webhook_url'] ?? null;
        
        if (!$webhookUrl) {
            Log::error("[TenantForwarder] No webhook URL configured for tenant", [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'config' => $tenantConfig
            ]);
            return false;
        }
        
        try {
            $payload = [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'event' => $dto->toArray(),
                'timestamp' => now()->toISOString(),
            ];
            
            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'MachineHub/1.0',
                    'X-Supplier' => $supplier,
                    'X-Tenant' => $tenant,
                ])
                ->post($webhookUrl, $payload);
            
            if ($response->successful()) {
                Log::info("[TenantForwarder] Successfully forwarded to tenant", [
                    'supplier' => $supplier,
                    'tenant' => $tenant,
                    'webhook_url' => $webhookUrl,
                    'event_id' => $dto->eventId,
                    'response_status' => $response->status()
                ]);
                return true;
            } else {
                Log::error("[TenantForwarder] Failed to forward to tenant", [
                    'supplier' => $supplier,
                    'tenant' => $tenant,
                    'webhook_url' => $webhookUrl,
                    'event_id' => $dto->eventId,
                    'response_status' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return false;
            }
            
        } catch (\Throwable $e) {
            Log::error("[TenantForwarder] Exception while forwarding to tenant", [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'webhook_url' => $webhookUrl,
                'event_id' => $dto->eventId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    /**
     * Forward to multiple tenants if configured
     */
    public function forwardToMultipleTenants(TelemetryDTO $dto, string $supplier, array $tenants): array
    {
        $results = [];
        
        foreach ($tenants as $tenant) {
            $results[$tenant] = $this->forwardToTenant($dto, $supplier, $tenant);
        }
        
        return $results;
    }
    
    /**
     * Get all configured tenants for a supplier
     */
    public function getConfiguredTenants(string $supplier): array
    {
        $supplierConfig = config("machinehub.suppliers.{$supplier}");
        return array_keys($supplierConfig['tenants'] ?? []);
    }
}

<?php

namespace App\Tenants;

use App\DTOs\TelemetryDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TenantForwarder
{
    /**
     * Forward telemetry to tenant-specific webhook
     * @return void
     * @throws \Illuminate\Validation\ValidationException When no webhook URL is configured or webhook rejects data
     * @throws \Illuminate\Http\Client\RequestException When webhook request fails
     * @throws \Exception When unexpected errors occur
     */
    public function forwardToTenant(TelemetryDTO $dto, string $supplier, string $tenant): void
    {
        $tenantConfig = TenantResolver::getTenantConfig($supplier, $tenant);

        if (!$tenantConfig) {
            $errorMessage = "No tenant configuration found for supplier '{$supplier}' and tenant '{$tenant}'";

            Log::error("[TenantForwarder] Configuration error - no tenant config found", [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'event_id' => $dto->eventId,
                'error_type' => 'configuration_error',
                'error_message' => $errorMessage,
                'location' => 'TenantForwarder::forwardToTenant() - missing tenant configuration',
                'solution' => 'Check machinehub configuration for tenant setup'
            ]);

            // Throw validation exception immediately
            $validator = validator([], []);
            $validator->errors()->add('tenant', $errorMessage);
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $webhookUrl = $tenantConfig['webhook_url'] ?? null;

        if (!$webhookUrl) {
            $payload = [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'event' => $dto->toArray(),
                'timestamp' => now()->toISOString(),
            ];

            $errorMessage = "No endpoint configured for tenant - please set {$tenant}_WEBHOOK_URL environment variable to enable forwarding";

            Log::error("[TenantForwarder] Configuration error - no webhook URL configured", [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'event_id' => $dto->eventId,
                'payload' => $payload,
                'error_type' => 'configuration_error',
                'error_message' => $errorMessage,
                'location' => 'TenantForwarder::forwardToTenant() - missing webhook URL configuration',
                'solution' => "Set {$tenant}_WEBHOOK_URL environment variable"
            ]);

            // Throw validation exception immediately - this is the professional practice
            $validator = validator([], []);
            $validator->errors()->add('webhook', $errorMessage);
            throw new \Illuminate\Validation\ValidationException($validator);
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
                    'response_status' => $response->status(),
                    'location' => 'TenantForwarder::forwardToTenant() - successful HTTP response'
                ]);
                return; // Success - method completes without exception
            } else {
                // Handle different HTTP error status codes
                $statusCode = $response->status();
                $responseBody = $response->body();

                if ($statusCode >= 400 && $statusCode < 500) {
                    // Client errors (4xx) - likely validation errors or bad request
                    Log::error("[TenantForwarder] Webhook rejected data (client error)", [
                        'supplier' => $supplier,
                        'tenant' => $tenant,
                        'webhook_url' => $webhookUrl,
                        'event_id' => $dto->eventId,
                        'response_status' => $statusCode,
                        'response_body' => $responseBody,
                        'payload' => $payload,
                        'location' => 'TenantForwarder::forwardToTenant() - webhook validation failed',
                        'error_type' => 'webhook_validation_error'
                    ]);

                    // Throw validation exception for client errors
                    $validator = validator([], []);
                    $validator->errors()->add('webhook', "Webhook rejected data with status {$statusCode}: " . $responseBody);
                    throw new \Illuminate\Validation\ValidationException($validator);
                } else {
                    // Server errors (5xx) - retryable errors
                    Log::error("[TenantForwarder] Webhook server error", [
                        'supplier' => $supplier,
                        'tenant' => $tenant,
                        'webhook_url' => $webhookUrl,
                        'event_id' => $dto->eventId,
                        'response_status' => $statusCode,
                        'response_body' => $responseBody,
                        'payload' => $payload,
                        'location' => 'TenantForwarder::forwardToTenant() - webhook server error',
                        'error_type' => 'webhook_server_error'
                    ]);

                    // Throw HTTP client exception for server errors (will trigger retry)
                    throw new \Illuminate\Http\Client\RequestException(
                        $response
                    );
                }
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Re-throw validation exceptions as-is
            throw $e;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            // Re-throw HTTP client exceptions as-is
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[TenantForwarder] Unexpected exception while forwarding to tenant", [
                'supplier' => $supplier,
                'tenant' => $tenant,
                'webhook_url' => $webhookUrl,
                'event_id' => $dto->eventId,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'location' => 'TenantForwarder::forwardToTenant() - unexpected error',
                'error_type' => 'unexpected_error'
            ]);

            // Wrap in generic exception
            throw new \Exception("Unexpected error during webhook forwarding: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Forward to multiple tenants if configured
     * @return array Results for each tenant (true for success, exception details for failures)
     */
    public function forwardToMultipleTenants(TelemetryDTO $dto, string $supplier, array $tenants): array
    {
        $results = [];

        foreach ($tenants as $tenant) {
            try {
                $this->forwardToTenant($dto, $supplier, $tenant);
                $results[$tenant] = true; // Success
            } catch (\Throwable $e) {
                $results[$tenant] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'exception_type' => get_class($e)
                ];
            }
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

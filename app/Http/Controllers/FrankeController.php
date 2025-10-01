<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Suppliers\AbstractSupplierAdapter;
use App\Tenants\TenantResolver;
use App\Tenants\TenantForwarder;
use App\Jobs\ForwardTelemetryJob;
use Illuminate\Support\Facades\Log;

class FrankeController extends Controller
{
    protected AbstractSupplierAdapter $adapter;
    protected TenantForwarder $tenantForwarder;

    public function __construct(TenantForwarder $tenantForwarder)
    {
        $this->adapter = $this->getAdapter();
        $this->tenantForwarder = $tenantForwarder;
    }

    /**
     * Handle Franke webhook with dynamic tenant resolution
     * URL: /webhook/franke/{tenant}
     */
    public function handle(Request $request, string $tenant): JsonResponse
    {
        $supplier = 'franke';

        Log::info("[FrankeController] Processing webhook", [
            'supplier' => $supplier,
            'tenant' => $tenant,
            'path' => $request->path()
        ]);

        // Check if Franke adapter exists
        if (!$this->adapter) {
            Log::error("[FrankeController] Franke adapter not implemented", [
                'supplier' => $supplier,
                'tenant' => $tenant
            ]);

            return response()->json([
                'error' => 'Franke webhook not implemented yet',
                'supplier' => $supplier,
                'tenant' => $tenant
            ], 501);
        }

        // Verify the webhook if needed
        $verification = $this->adapter->verify($request);
        if ($verification instanceof JsonResponse) {
            return $verification;
        }

        if (!$verification) {
            Log::warning("[FrankeController] Webhook verification failed", [
                'supplier' => $supplier,
                'tenant' => $tenant
            ]);

            return response()->json(['error' => 'Verification failed'], 400);
        }

        // Process the webhook data
        $events = $this->extractEvents($request);

        if (empty($events)) {
            Log::warning("[FrankeController] No events found in webhook", [
                'supplier' => $supplier,
                'tenant' => $tenant
            ]);

            return response()->json(['message' => 'No events to process'], 200);
        }

        $processedCount = 0;
        $errors = [];

        foreach ($events as $event) {
            try {
                $dto = $this->adapter->handleEvent($event);

                if ($dto) {
                    // Dispatch forwarding job for reliable delivery
                    ForwardTelemetryJob::dispatch($supplier, $tenant, $dto);

                    $processedCount++;
                    Log::info("[FrankeController] Event dispatched for forwarding", [
                        'supplier' => $supplier,
                        'tenant' => $tenant,
                        'event_id' => $dto->eventId,
                        'type' => $dto->type
                    ]);
                }
            } catch (\Throwable $e) {
                $errors[] = "Error processing event: " . $e->getMessage();
                Log::error("[FrankeController] Error processing event", [
                    'supplier' => $supplier,
                    'tenant' => $tenant,
                    'error' => $e->getMessage(),
                    'event' => $event
                ]);
            }
        }

        $response = [
            'message' => 'Franke webhook processed',
            'processed_count' => $processedCount,
            'total_events' => count($events),
            'supplier' => $supplier,
            'tenant' => $tenant
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, 200);
    }

    /**
     * Get Franke adapter (if implemented)
     */
    protected function getAdapter(): ?AbstractSupplierAdapter
    {
        $adapterClass = "App\\Suppliers\\FrankeAdapter";

        if (class_exists($adapterClass)) {
            return new $adapterClass();
        }

        return null;
    }

    /**
     * Extract events from Franke webhook request
     */
    protected function extractEvents(Request $request): array
    {
        $data = $request->json()->all();

        // Franke sends both single event and array of events
        if (isset($data['eventType']) || isset($data['event'])) {
            return [$data];
        }

        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        return [];
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Suppliers\WMFAdapter;
use App\Tenants\TenantResolver;
use App\Tenants\TenantForwarder;
use App\Jobs\ForwardTelemetryJob;
use Illuminate\Support\Facades\Log;

class WMFController extends Controller
{
    protected WMFAdapter $adapter;
    protected TenantForwarder $tenantForwarder;

    public function __construct(WMFAdapter $adapter, TenantForwarder $tenantForwarder)
    {
        $this->adapter = $adapter;
        $this->tenantForwarder = $tenantForwarder;
    }

    /**
     * Handle WMF webhook with dynamic tenant resolution
     * URL: /webhook/wmf/{tenant}
     */
    public function handle(Request $request, string $tenant): JsonResponse
    {
        $supplier = 'wmf';

        Log::info("[WMFController] Processing webhook", [
            'supplier' => $supplier,
            'tenant' => $tenant,
            'path' => $request->path()
        ]);

        // Verify the webhook if needed
        $verification = $this->adapter->verify($request);
        if ($verification instanceof JsonResponse) {
            return $verification;
        }

        if (!$verification) {
            Log::warning("[WMFController] Webhook verification failed", [
                'supplier' => $supplier,
                'tenant' => $tenant
            ]);

            return response()->json(['error' => 'Verification failed'], 400);
        }

        // Process the webhook data
        $events = $this->extractEvents($request);

        if (empty($events)) {
            Log::warning("[WMFController] No events found in webhook", [
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
                    Log::info("[WMFController] Event dispatched for forwarding", [
                        'supplier' => $supplier,
                        'tenant' => $tenant,
                        'event_id' => $dto->eventId,
                        'type' => $dto->type
                    ]);
                }
            } catch (\Throwable $e) {
                $errors[] = "Error processing event: " . $e->getMessage();
                Log::error("[WMFController] Error processing event", [
                    'supplier' => $supplier,
                    'tenant' => $tenant,
                    'error' => $e->getMessage(),
                    'event' => $event
                ]);
            }
        }

        $response = [
            'message' => 'WMF webhook processed',
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
     * Extract events from WMF webhook request
     */
    protected function extractEvents(Request $request): array
    {
        $data = $request->json()->all();

        // WMF sends both single event and array of events
        if (isset($data['eventType']) || isset($data['event'])) {
            return [$data];
        }

        if (is_array($data) && isset($data[0])) {
            return $data;
        }

        return [];
    }
}

<?php

namespace App\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DTOs\TelemetryDTO;
use Illuminate\Support\Facades\Log;

class FrankeAdapter extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return 'franke';
    }

    public function mode(): string
    {
        return 'webhook';
    }

    public function verify(?Request $request): JsonResponse|bool
    {
        // Implement Franke webhook verification logic
        // For now, just return true
        return true;
    }

    public function handleEvent(array $event): ?TelemetryDTO
    {
        Log::info("[FrankeAdapter] Processing Franke event", [
            'event' => $event
        ]);

        // TODO: Implement Franke-specific event processing
        // For now, return a basic DTO
        return new TelemetryDTO(
            type: 'FrankeEvent',
            eventId: $event['id'] ?? uniqid(),
            deviceId: $event['device_id'] ?? null,
            occurredAt: $event['timestamp'] ?? null,
            payload: $event
        );
    }
}


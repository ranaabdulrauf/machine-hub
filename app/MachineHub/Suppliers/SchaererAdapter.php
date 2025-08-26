<?php

namespace App\MachineHub\Suppliers\Schaerer;

use App\MachineHub\Core\AbstractSupplierAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SchaererAdapter extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return 'schaerer';
    }

    public function verify(Request $request): bool|JsonResponse
    {
        $events = $request->json()->all();
        $first  = $events[0] ?? null;

        // Subscription validation handshake
        if (
            ($first['eventType'] ?? null) === 'Microsoft.EventGrid.SubscriptionValidationEvent'
        ) {
            $code = $first['data']['validationCode'] ?? null;

            return $code
                ? response()->json(['validationResponse' => $code])
                : response()->json(['error' => 'Missing validation code'], 400);
        }

        return true;
    }

    protected function handleDispensing(array $event): array
    {
        $data = $event['data'] ?? [];

        return [
            'type'       => 'Dispensing',
            'eventId'    => $event['id'] ?? null,
            'deviceId'   => $data['DeviceId'] ?? null,
            'occurredAt' => $data['TelemetryInformation']['Timestamp'] ?? null,
            'payload'    => $data,
        ];
    }

    protected function handleMachineEvent(array $event): array
    {
        $data = $event['data'] ?? [];

        return [
            'type'    => 'MachineEvent',
            'eventId' => $event['id'] ?? null,
            'deviceId' => $data['DeviceId'] ?? null,
            'payload' => $data,
        ];
    }
}

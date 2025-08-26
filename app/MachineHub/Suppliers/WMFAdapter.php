<?php

namespace App\MachineHub\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\MachineHub\Core\AbstractSupplierAdapter;
use App\MachineHub\Core\Contracts\SupplierAdapter;

class WMFAdapter extends AbstractSupplierAdapter
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

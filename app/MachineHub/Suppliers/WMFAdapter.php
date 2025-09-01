<?php

namespace App\MachineHub\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\MachineHub\Core\DTO\TelemetryDTO;
use App\MachineHub\Suppliers\AbstractSupplierAdapter;

class WMFAdapter extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return 'wmf';
    }

    public function verify(Request $request): bool|JsonResponse
    {
        $events = $request->json()->all();
        $first  = $events[0] ?? null;

        // Subscription validation handshake (EventGrid style)
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

    protected function handleDispensing(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] Dispensing raw", $event);

        return new TelemetryDTO(
            type: 'Dispensing',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $data['TelemetryInformation']['Timestamp'] ?? null,
            payload: $data
        );
    }

    protected function handleMachineEvent(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] MachineEvent raw", $event);

        return new TelemetryDTO(
            type: 'MachineEvent',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $event['eventTime'] ?? null,
            payload: $data
        );
    }

    protected function handleDiagnostics(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] Diagnostics raw", $event);

        return new TelemetryDTO(
            type: 'Diagnostics',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $event['eventTime'] ?? null,
            payload: $data
        );
    }

    protected function handleModemMessage(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] Diagnostics raw", $event);

        return new TelemetryDTO(
            type: 'ModemMessage',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $data['TelemetryInformation']['Timestamp'] ?? null,
            payload: $data
        );
    }

    protected function handleStatistics(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] Diagnostics raw", $event);

        return new TelemetryDTO(
            type: 'Statistics',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $data['TelemetryInformation']['Timestamp'] ?? null,
            payload: $data
        );
    }

    protected function handleMachineTwin(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] Diagnostics raw", $event);

        return new TelemetryDTO(
            type: 'MachineTwin',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $data['Time'] ?? null, // <-- from docs
            payload: $data
        );
    }

    protected function handleMachineModemTwin(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[WMF] Diagnostics raw", $event);

        return new TelemetryDTO(
            type: 'MachineModemTwin',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: null, // twin is a snapshot
            payload: $data
        );
    }
}

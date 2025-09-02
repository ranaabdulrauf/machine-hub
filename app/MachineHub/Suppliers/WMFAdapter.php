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


    public function handleEvent(array $event): ?TelemetryDTO
    {
        $type = $event['eventType'] ?? $event['event'] ?? null;

        if (!$type) {
            Log::warning("[{$this->name()}] Missing event type", ['event' => $event]);
            return null;
        }

        $method = 'handle' . str_replace(' ', '', ucwords(strtolower($type)));

        if (method_exists($this, $method)) {
            return $this->{$method}($event);
        }

        return $this->handleUnknownEvent($event);
    }

    protected function handleDispensing(array $event): ?TelemetryDTO
    {
        $data = $event['data'] ?? [];

        Log::info("[{$this->name()}] DispensingEvent: ", $event);

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

        Log::info("[{$this->name()}] MachineEvent: ", $event);

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

        Log::info("[{$this->name()}] DiagnosticsEvent:", $event);

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

        Log::info("[{$this->name()}] ModemEvent: ", $event);

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

        Log::info("[{$this->name()}] StatisticsEvent: ", $event);

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

        Log::info("[{$this->name()}] MachineTwinEvent: ", $event);

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

        Log::info("[{$this->name()}] MachineModemEvent: ", $event);

        return new TelemetryDTO(
            type: 'MachineModemTwin',
            eventId: $event['id'] ?? '',
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: null, // twin is a snapshot
            payload: $data
        );
    }
}

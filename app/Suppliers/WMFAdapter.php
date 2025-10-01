<?php

namespace App\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\DTOs\TelemetryDTO;

class WMFAdapter extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return 'wmf';
    }

    public function mode(): string
    {
        return 'webhook';
    }

    public function verify(?Request $request): JsonResponse|bool
    {
        if (!$request) {
            return false;
        }

        // Check for Azure Event Grid subscription validation header
        $aegEventType = $request->header('aeg-event-type');
        if ($aegEventType === 'SubscriptionValidation') {
            return $this->handleSubscriptionValidation($request);
        }

        $events = $request->json()->all();
        $first  = $events[0] ?? null;

        // Handle both single event and array of events
        if (!$first && !empty($events)) {
            $first = $events;
        }

        // Check for subscription validation event in the body
        if (($first['eventType'] ?? null) === 'Microsoft.EventGrid.SubscriptionValidationEvent') {
            return $this->handleSubscriptionValidation($request);
        }

        // For regular events, verify we have valid event data
        if (empty($events) || (!isset($first['eventType']) && !isset($first['event']))) {
            Log::warning("[WMFAdapter] Invalid event structure", [
                'events' => $events,
                'headers' => $request->headers->all()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Handle Azure Event Grid subscription validation
     */
    protected function handleSubscriptionValidation(Request $request): JsonResponse
    {
        $events = $request->json()->all();
        $first  = $events[0] ?? null;

        // Handle both single event and array of events
        if (!$first && !empty($events)) {
            $first = $events;
        }

        $validationCode = $first['data']['validationCode'] ?? null;
        $validationUrl = $first['data']['validationUrl'] ?? null;

        Log::info("[WMFAdapter] Handling Azure Event Grid subscription validation", [
            'validationCode' => $validationCode,
            'validationUrl' => $validationUrl,
            'eventId' => $first['id'] ?? null,
            'topic' => $first['topic'] ?? null
        ]);

        if (!$validationCode) {
            Log::warning("[WMFAdapter] Missing validation code in subscription validation event");
            return response()->json(['error' => 'Missing validation code'], 400);
        }

        // Return the validation response as required by Azure Event Grid
        // Must return HTTP 200 OK status code
        return response()->json(['validationResponse' => $validationCode], 200);
    }

    public function handleEvent(array $event): ?TelemetryDTO
    {
        $type = $event['eventType'] ?? $event['event'] ?? null;

        if (!$type) {
            Log::warning("[{$this->name()}] Missing event type", ['event' => $event]);
            return null;
        }

        // Skip subscription validation events
        if ($type === 'Microsoft.EventGrid.SubscriptionValidationEvent') {
            Log::info("[{$this->name()}] Skipping subscription validation event");
            return null;
        }

        // Map WMF event types to handler methods
        $method = $this->getHandlerMethod($type);

        if (method_exists($this, $method)) {
            return $this->{$method}($event);
        }

        return $this->handleUnknownEvent($event);
    }

    /**
     * Map WMF event types to handler method names
     */
    protected function getHandlerMethod(string $eventType): string
    {
        $mapping = [
            'Dispensing' => 'handleDispensing',
            'MachineEvent' => 'handleMachineEvent',
            'Diagnostics' => 'handleDiagnostics',
            'ModemMessage' => 'handleModemMessage',
            'Statistics' => 'handleStatistics',
            'MachineTwin' => 'handleMachineTwin',
            'MachineModemTwin' => 'handleMachineModemTwin',
        ];

        return $mapping[$eventType] ?? 'handleUnknownEvent';
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

    /**
     * Handle unknown event types
     */
    protected function handleUnknownEvent(array $event): ?TelemetryDTO
    {
        $type = $event['eventType'] ?? $event['event'] ?? 'Unknown';
        $data = $event['data'] ?? [];

        Log::info("[{$this->name()}] Unknown event type: {$type}", $event);

        return new TelemetryDTO(
            type: 'WMFEvent', // Generic WMF event type
            eventId: $event['id'] ?? uniqid('wmf_'),
            deviceId: $data['DeviceId'] ?? null,
            occurredAt: $event['eventTime'] ?? $data['Timestamp'] ?? now(),
            payload: $event
        );
    }
}

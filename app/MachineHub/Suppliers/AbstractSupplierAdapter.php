<?php

namespace App\MachineHub\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\MachineHub\Core\DTO\TelemetryDTO;
use App\MachineHub\Core\Contracts\SupplierAdapter;

abstract class AbstractSupplierAdapter implements SupplierAdapter
{


    public function name(): string
    {
        return 'Abstract';
    }

    /**
     * Default: allow all requests.
     * Override if supplier requires signature/IP/handshake checks.
     */
    public function verify(Request $request): bool|JsonResponse
    {
        return true;
    }

    /**
     * Default dispatcher: converts "EventType" → "handleEventType".
     * Suppliers can override if they need custom dispatch logic.
     */
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

    /**
     * Default unknown handler.
     */
    protected function handleUnknownEvent(array $event): ?TelemetryDTO
    {
        Log::warning("[{$this->name()}] UnknownEvent: ", [
            'eventType' => $event['eventType'] ?? $event['event'] ?? 'undefined',
            'event'     => $event,
        ]);
        return null;
    }
}

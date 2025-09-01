<?php

namespace App\MachineHub\Suppliers;

use App\MachineHub\Core\Contracts\SupplierAdapter;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

abstract class AbstractSupplierAdapter implements SupplierAdapter
{
    /**
     * Verify request (signature, handshake, etc.)
     * Default: always true.
     */
    public function verify(Request $request): bool|JsonResponse
    {
        return true;
    }

    /**
     * Event dispatcher.
     */
    public function handleEvent(array $event): ?array
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
     * supplier name.
     */
    public function name(): string
    {
        return 'unknown';
    }

    /**
     * Default unknown event handler.
     */
    protected function handleUnknownEvent(array $event): ?array
    {
        Log::warning("[{$this->name()}] Unknown event type", [
            'eventType' => $event['eventType'] ?? $event['event'] ?? 'undefined',
            'event'     => $event,
        ]);
        return null;
    }
}

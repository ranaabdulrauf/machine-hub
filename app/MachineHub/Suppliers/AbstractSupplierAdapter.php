<?php

namespace App\MachineHub\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\MachineHub\Core\DTO\TelemetryDTO;
use App\MachineHub\Core\Contracts\SupplierAdapter;

abstract class AbstractSupplierAdapter
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    abstract public function name(): string;
    abstract public function mode(): string; // "webhook" | "api"
    abstract public function verify(?Request $request): JsonResponse|bool;

    /**
     * Handle a single webhook event (for mode=webhook).
     */
    public function handleEvent(array $event): ?TelemetryDTO
    {
        return $this->handleUnknownEvent($event);
    }

    /**
     * Handle API polling (for mode=api).
     * Default: no-op.
     */
    public function handleApi(): void
    {
        // no-op, override in API suppliers
    }

    protected function handleUnknownEvent(array $event): ?TelemetryDTO
    {
        Log::warning("[{$this->name()}] UnknownEvent", [
            'eventType' => $event['eventType'] ?? $event['event'] ?? 'undefined',
            'event'     => $event,
        ]);
        return null;
    }
}


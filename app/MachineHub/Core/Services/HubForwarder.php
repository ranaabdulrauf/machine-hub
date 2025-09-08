<?php

namespace App\MachineHub\Core\Services;

use App\MachineHub\Core\DTO\TelemetryDTO;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HubForwarder
{
    protected string $url;

    public function __construct()
    {
        // You can also inject via config('services.webhook.url')
        $this->url = $url ?? config('services.webhook.url');
    }

    public function send(TelemetryDTO $dto, string $supplier): void
    {

        if ($this->url === null) {
            Log::warning("[WebhookForwarder] No URL configured, skipping send", [
                'supplier' => $supplier,
                'event'    => $dto->toArray(),
            ]);
            return;
        }
        
        try {
            Http::post($this->url, [
                'supplier' => $supplier,
                'event'    => $dto->toArray(),
            ]);
        } catch (\Throwable $e) {
            Log::error("[WebhookForwarder] Failed to send telemetry", [
                'supplier' => $supplier,
                'event'    => $dto->toArray(),
                'error'    => $e->getMessage(),
            ]);
        }
    }
}

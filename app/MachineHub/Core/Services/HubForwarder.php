<?php

namespace App\MachineHub\Core\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\MachineHub\Core\DTO\TelemetryDTO;

class HubForwarder
{
    public function send(TelemetryDTO $dto, string $supplier): void
    {
        $url = "";

        $payload = [
            'supplier' => $supplier,
            'event'    => $dto->toArray(),
        ];

        try {
            Http::post($url, $payload);
            Log::info("[HubForwarder] Forwarded DTO", $payload);
        } catch (\Throwable $e) {
            Log::error("[HubForwarder] Failed to forward DTO", [
                'error'    => $e->getMessage(),
                'supplier' => $supplier,
                'event'    => $dto->toArray(),
            ]);
        }
    }
}

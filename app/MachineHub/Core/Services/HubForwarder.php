<?php

namespace App\MachineHub\Core\Services;

use Illuminate\Support\Facades\Log;

class HubForwarder
{
    public function send(array $dto, string $supplier): void
    {
        // In real-world: HTTP client to central hub API
        Log::info("[HubForwarder] Forwarded DTO", [
            'supplier' => $supplier,
            'dto'      => $dto,
        ]);
    }
}

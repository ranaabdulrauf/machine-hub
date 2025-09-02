<?php

namespace App\MachineHub\Core\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\MachineHub\Core\DTO\TelemetryDTO;

interface SupplierAdapter
{

    /**
     * Supplier short name, used for registry & forwarding.
     */
    public function name(): string;

    /**
     * Verify request (signature, handshake, origin, etc.)
     * Return:
     *  - true if verification passed
     *  - JsonResponse if a handshake response must be returned
     *  - false if verification failed
     */
    public function verify(Request $request): bool|JsonResponse;

    /**
     * Handle a supplier event and return a mapped DTO.
     */
    public function handleEvent(array $event): ?TelemetryDTO;
}

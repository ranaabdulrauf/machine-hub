<?php

namespace App\MachineHub\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\MachineHub\Suppliers\AbstractSupplierAdapter;

class WMFAdapter extends AbstractSupplierAdapter
{
    public function name(): string
    {
        return 'WMF';
    }

    public function verify(Request $request): bool|JsonResponse
    {
        $events = $request->json()->all();
        $first  = $events[0] ?? null;

        // Subscription validation handshake
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

    protected function handleDispensing(array $event): ?array
    {
        Log::info('[WMF] Dispensing', $event);
        return $event;
    }
    protected function handleMachineEvent(array $event): ?array
    {
        Log::info('[WMF] MachineEvent', $event);
        return $event;
    }
    protected function handleDiagnostics(array $event): ?array
    {
        Log::info('[WMF] Diagnostics', $event);
        return $event;
    }
    protected function handleModemMessage(array $event): ?array
    {
        Log::info('[WMF] ModemMessage', $event);
        return $event;
    }
    protected function handleStatistics(array $event): ?array
    {
        Log::info('[WMF] Statistics', $event);
        return $event;
    }
    protected function handleMachineTwin(array $event): ?array
    {
        Log::info('[WMF] MachineTwin', $event);
        return $event;
    }
    protected function handleMachineModemTwin(array $event): ?array
    {
        Log::info('[WMF] MachineModemTwin', $event);
        return $event;
    }
}

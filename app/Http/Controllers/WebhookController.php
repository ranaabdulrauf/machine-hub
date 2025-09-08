<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\MachineHub\Core\SupplierRegistry;

class WebhookController extends Controller
{
    public function handle(Request $request, string $supplier)
    {
        // Resolve the registry from the container
        $registry = app(SupplierRegistry::class);

        // Get the adapter for this supplier
        $adapter = $registry->resolve($supplier);

        // Step 1: Verify or handshake
        $verification = $adapter->verify($request);
        if ($verification instanceof \Illuminate\Http\JsonResponse) {
            return $verification; // return handshake response
        }
        if ($verification === false) {
            return response()->json(['error' => 'Verification failed'], 403);
        }

        // Step 2: Parse events
        $events = $request->json()->all();
        if (!is_array($events) || empty($events)) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Step 3: Dispatch to adapter handlers
        foreach ($events as $event) {
            $adapter->handleEvent($event);
        }

        return response()->json(['status' => 'processed'], 200);
    }
}

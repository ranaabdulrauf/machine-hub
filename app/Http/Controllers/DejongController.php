<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class DejongController extends Controller
{
    /**
     * Handle Dejong webhook requests
     * Note: Dejong doesn't support webhooks, this is just for consistency
     */
    public function handle(Request $request, string $tenant): JsonResponse
    {
        Log::info("[DejongController] Webhook request received (Dejong doesn't support webhooks)", [
            'supplier' => 'dejong',
            'tenant' => $tenant,
            'path' => $request->path()
        ]);

        return response()->json([
            'error' => 'Dejong does not support webhooks',
            'message' => 'Dejong uses API polling instead of webhooks',
            'supplier' => 'dejong',
            'tenant' => $tenant
        ], 405);
    }
}

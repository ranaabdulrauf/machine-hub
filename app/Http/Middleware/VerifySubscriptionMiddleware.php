<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifySubscriptionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $supplier = strtolower($request->route('supplier'));

        $incomingName = $request->header('aeg-subscription-name');
        $expectedName = config("machinehub.suppliers.$supplier.subscription_name");

        if (!$expectedName || $incomingName !== $expectedName) {
            Log::warning("[Webhook] Invalid subscription name", [
                'supplier' => $supplier,
                'incoming' => $incomingName,
                'expected' => $expectedName,
                'ip'       => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid subscription name'], 403);
        }

        return $next($request);
    }
}

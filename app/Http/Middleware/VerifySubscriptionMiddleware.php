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

        // Get subscription name from header (Azure Event Grid uses aeg-subscription-name)
        $incomingName = $request->header('aeg-subscription-name');
        $expectedName = config("machinehub.suppliers.$supplier.options.subscription_name");

        Log::info("[VerifySubscriptionMiddleware] Checking subscription", [
            'supplier' => $supplier,
            'incoming' => $incomingName,
            'expected' => $expectedName,
            'ip' => $request->ip(),
        ]);

        // If no subscription name is configured, skip validation
        if (!$expectedName) {
            Log::warning("[VerifySubscriptionMiddleware] No subscription name configured for supplier", [
                'supplier' => $supplier
            ]);
            return $next($request);
        }

        // If no incoming subscription name, reject
        if (!$incomingName) {
            Log::warning("[VerifySubscriptionMiddleware] Missing subscription name header", [
                'supplier' => $supplier,
                'headers' => $request->headers->all()
            ]);
            return response()->json(['error' => 'Missing subscription name'], 403);
        }

        // Check if subscription name matches
        if ($incomingName !== $expectedName) {
            Log::warning("[VerifySubscriptionMiddleware] Invalid subscription name", [
                'supplier' => $supplier,
                'incoming' => $incomingName,
                'expected' => $expectedName,
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid subscription name'], 403);
        }

        Log::info("[VerifySubscriptionMiddleware] Subscription validation successful", [
            'supplier' => $supplier,
            'subscription' => $incomingName
        ]);

        return $next($request);
    }
}

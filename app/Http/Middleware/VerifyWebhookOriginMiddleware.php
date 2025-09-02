<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookOriginMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $allowedIps = config("machinehub.suppliers.{$request->route('supplier')}.allowed_ips", []);

        if (! in_array($request->ip(), $allowedIps, true)) {
            Log::warning("[Webhook] Blocked request from invalid IP", [
                'ip'       => $request->ip(),
                'supplier' => $request->route('supplier'),
                'allowed'  => $allowedIps,
            ]);
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}

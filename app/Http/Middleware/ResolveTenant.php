<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Tenants\TenantResolver;
use Illuminate\Support\Facades\Log;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next): \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $tenant = TenantResolver::resolve($request);

        if (!$tenant) {
            Log::warning("[ResolveTenant] No tenant resolved from request", [
                'path' => $request->path(),
                'method' => $request->method(),
                'headers' => $request->headers->all(),
                'query' => $request->query->all()
            ]);

            return response()->json([
                'error' => 'Tenant not found',
                'message' => 'Unable to determine tenant from request'
            ], 400);
        }

        // Add tenant to request for use in controllers
        $request->merge(['resolved_tenant' => $tenant]);

        Log::info("[ResolveTenant] Tenant resolved successfully", [
            'tenant' => $tenant,
            'path' => $request->path()
        ]);

        return $next($request);
    }
}

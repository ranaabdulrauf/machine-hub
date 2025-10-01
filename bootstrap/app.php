<?php

use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\VerifySubscriptionMiddleware;
use App\Http\Middleware\ResolveTenant;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhook/*',
            'api/poll/*',
        ]);
        $middleware->alias([
            'verify.subscription' => VerifySubscriptionMiddleware::class,
            'resolve.tenant' => ResolveTenant::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule) {
        // Process all API suppliers every 5 minutes
        $schedule->job(new \App\Jobs\ProcessAllApiSuppliersJob())->everyFiveMinutes();

        // Forward all API suppliers telemetry every 2 minutes
        $schedule->job(new \App\Jobs\ForwardAllApiSuppliersTelemetryJob())->everyTwoMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

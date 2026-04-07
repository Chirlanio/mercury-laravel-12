<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\ActivityLogMiddleware::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'activity-log' => \App\Http\Middleware\ActivityLogMiddleware::class,
            'integration.auth' => \App\Http\Middleware\AuthenticateIntegration::class,
            'tenant.active' => \App\Http\Middleware\CheckTenantActive::class,
            'tenant.module' => \App\Http\Middleware\CheckTenantModule::class,
            'plan.limit' => \App\Http\Middleware\CheckPlanLimit::class,
            'terms.accepted' => \App\Http\Middleware\EnsureTermsAccepted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

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
        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Lets the same-origin web app (Blade + Vue, served by this app)
        // authenticate via a Sanctum session cookie instead of a Bearer
        // token. Only kicks in for requests from SANCTUM_STATEFUL_DOMAINS
        // (config/sanctum.php) — mobile's token-based requests are
        // untouched, since Sanctum supports both simultaneously.
        $middleware->prependToGroup('api', \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);

        $middleware->appendToGroup('api', \App\Http\Middleware\TrackUserActivity::class);
        $middleware->appendToGroup('api', \App\Http\Middleware\BlockDuringMaintenance::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

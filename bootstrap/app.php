<?php

use App\Http\Middleware\BlockDuringMaintenance;
use App\Http\Middleware\TrackUserActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // App Platform terminates TLS and forwards plain HTTP to nginx on
        // 8080. Without this, Laravel sees http:// and generates http URLs —
        // which invalidates the signed email-verification links (the scheme
        // is part of the signature) and marks OAuth/redirect URLs insecure.
        // Safe to trust '*' here: the container is only reachable through
        // App Platform's own ingress.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);

        // Lets the same-origin web app (Blade + Vue, served by this app)
        // authenticate via a Sanctum session cookie instead of a Bearer
        // token. Only kicks in for requests from SANCTUM_STATEFUL_DOMAINS
        // (config/sanctum.php) — mobile's token-based requests are
        // untouched, since Sanctum supports both simultaneously.
        $middleware->prependToGroup('api', EnsureFrontendRequestsAreStateful::class);

        $middleware->appendToGroup('api', TrackUserActivity::class);
        $middleware->appendToGroup('api', BlockDuringMaintenance::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Without this, exceeding post_max_size (e.g. a long sermon/podcast
        // upload) surfaces as an uncaught PostTooLargeException — a raw
        // Laravel exception trace in production — instead of a clean error
        // the admin/creator upload forms already know how to display
        // (they read response.data.message).
        $exceptions->render(function (PostTooLargeException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This upload is too large. Please use a smaller file.',
                ], 413);
            }
        });
    })->create();

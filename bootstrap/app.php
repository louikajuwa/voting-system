<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only trust forwarded headers from known proxies (the bundled nginx on the
        // private Docker network). Override with TRUSTED_PROXIES (comma list / CIDR).
        $trusted = trim((string) env('TRUSTED_PROXIES', ''));
        // Only X-Forwarded-For/Proto are honoured (Host/Port headers are ignored).
        $middleware->trustProxies(at: $trusted === '' || $trusted === 'null'
            ? ['172.16.0.0/12', '10.0.0.0/8', '192.168.0.0/16', '127.0.0.1']
            : array_values(array_filter(array_map('trim', explode(',', $trusted)))),
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->appendToGroup('web', SecurityHeaders::class);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\Team4ServiceSignature;
use App\Http\Middleware\EnsureTeam4Role;
use App\Http\Middleware\EnsureTeam4Permission;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [HandleInertiaRequests::class]);

        $middleware->trustProxies(at: '*', headers:
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO
        );
        $middleware->alias([
            'team4.role'       => EnsureTeam4Role::class,
            'team4.permission' => EnsureTeam4Permission::class,
            'team4.signature'  => Team4ServiceSignature::class,
        ]);

        // Rutas de integracion (E3, E7, webhooks): exentas de CSRF, autenticadas por HMAC
        $middleware->validateCsrfTokens(except: [
            'api/equipo4/*',
            'api/equipo4',
            'api/v1/inventory/*',
            'api/v1/inventory',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Manejo de excepciones del proyecto.
    })->create();
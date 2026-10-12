<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Fuerza Accept: application/json en rutas /api/* para que las validaciones
 * devuelvan JSON en lugar de redirect 302 cuando el cliente no manda el header.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/*')) {
            $request->headers->set('Accept', 'application/json');
        }
        return $next($request);
    }
}
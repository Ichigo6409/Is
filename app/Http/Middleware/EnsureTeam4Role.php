<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTeam4Role
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        $role = $user?->role ?? $request->header('X-User-Role');

        // Prototipo: Equipo 1 será la fuente de verdad de roles en integración.
        if (!$user && !$request->header('X-User-Role')) {
            return response()->json(['message'=>'No autenticado.'], 401);
        }
        if ($roles && !in_array($role, $roles, true)) {
            return response()->json(['message'=>'No autorizado para esta operación.'], 403);
        }
        return $next($request);
    }
}

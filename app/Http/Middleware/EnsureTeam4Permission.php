<?php

namespace App\Http\Middleware;

use App\Services\IdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeam4Permission
{
    public function __construct(protected IdentityService $identity) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $userId = (string) $request->attributes->get('team4_user_id')
            ?: $this->identity->resolveCurrentUserId();

        if (!$this->identity->userCan($userId, $permission)) {
            return redirect()->route('equipo4.not-authorized', [
                'reason'     => 'permission_denied',
                'permission' => $permission,
            ]);
        }

        return $next($request);
    }
}
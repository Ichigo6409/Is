<?php

namespace App\Http\Middleware;

use App\Services\IdentityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeam4Role
{
    public function __construct(protected IdentityService $identity) {}

    public function handle(Request $request, Closure $next): Response
    {
        $userId = $this->identity->resolveCurrentUserId();
        $user = $this->identity->getUser($userId);

        if ($user === null) return redirect()->route('equipo4.not-authorized', ['reason' => 'user_not_found']);

        $role = (string) ($user['role'] ?? '');
        if (!$this->identity->isAuthorized($role)) return redirect()->route('equipo4.not-authorized', ['reason' => 'role_not_allowed']);

        $request->attributes->set('team4_user_id', $userId);
        $request->attributes->set('team4_user_role', $role);

        return $next($request);
    }
}

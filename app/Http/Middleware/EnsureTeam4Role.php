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
        $user   = $this->identity->getUser($userId);

        if ($user === null) {
            return redirect()->route('equipo4.not-authorized', ['reason' => 'user_not_found']);
        }

        $role = (string) ($user['role'] ?? '');
        if (!$this->identity->isAuthorized($role)) {
            return redirect()->route('equipo4.not-authorized', ['reason' => 'role_not_allowed']);
        }

        // Validar scope si es por negocio
        $scope           = $user['role_scope'] ?? 'global';
        $roleBusinessId  = $user['role_business_id'] ?? null;
        $currentBusiness = (string) config('team4.business_id', 'BUS-CD-SOUV-001');

        if ($scope === 'business') {
            if ($roleBusinessId === null) {
                return redirect()->route('equipo4.not-authorized', ['reason' => 'business_scope_missing']);
            }
            if ($roleBusinessId !== $currentBusiness) {
                return redirect()->route('equipo4.not-authorized', ['reason' => 'business_mismatch']);
            }
        }

        $request->attributes->set('team4_user_id', $userId);
        $request->attributes->set('team4_user_role', $role);
        $request->attributes->set('team4_user_scope', $scope);

        return $next($request);
    }
}
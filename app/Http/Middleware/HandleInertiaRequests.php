<?php

namespace App\Http\Middleware;

use App\Services\IdentityService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $identity = app(IdentityService::class);
        $userId   = $identity->resolveCurrentUserId();
        $shared   = $identity->getSharedUser($userId);

        // Si el usuario no existe (o no tiene roles validos), no comparte
        // permisos. La UI vera la pantalla de "no autorizado".
        $roles       = $shared['roles'] ?? [];
        $role        = $shared['role']  ?? null;
        $permissions = $identity->getPermissions($roles);
        $kpis        = $identity->getDashboardKpis($roles);

        $urgentAlertsCount = 0;
        try {
            $urgentAlertsCount = \Illuminate\Support\Facades\DB::connection('mongodb')
                ->getCollection('stock_alerts')
                ->countDocuments([
                    'business_id' => config('team4.business_id'),
                    'status'      => 'ACTIVE',
                    'priority'    => ['$in' => ['HIGH', 'CRITICAL']],
                ]);
        } catch (\Throwable $e) {
            $urgentAlertsCount = 0;
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user'            => $shared,
                'role'            => $role,        // primario (string, para UI compacta)
                'roles'           => $roles,       // array completo
                'is_authorized'   => $identity->isAuthorized($roles),
                'permissions'     => $permissions,
                'dashboard_kpis'  => $kpis,
            ],
            'alertsCount' => $urgentAlertsCount,
            'csrfToken'   => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
        ];
    }
}
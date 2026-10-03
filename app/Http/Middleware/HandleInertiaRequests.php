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
        $userId = $identity->resolveCurrentUserId();
        $sharedUser = $identity->getSharedUser($userId);

        $role = $sharedUser['role'] ?? null;
        $permissions = $identity->getPermissions($role);
        $dashboardKpis = $identity->getDashboardKpis($role);

        $urgentAlertsCount = 0;
        try {
            $urgentAlertsCount = \Illuminate\Support\Facades\DB::connection('mongodb')
                ->getCollection('stock_alerts')
                ->countDocuments([
                    'business_id' => config('team4.business_id'),
                    'status' => 'ACTIVE',
                    'priority' => ['$in' => ['HIGH', 'CRITICAL']],
                ]);
        } catch (\Throwable $e) { $urgentAlertsCount = 0; }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $sharedUser,
                'role' => $role,
                'is_authorized' => $identity->isAuthorized($role),
                'permissions' => $permissions,
                'dashboard_kpis' => $dashboardKpis,
            ],
            'alertsCount' => $urgentAlertsCount,
            'csrfToken' => csrf_token(),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}

<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        // Contar alertas urgentes para el badge
        $urgentAlertsCount = 0;
        try {
            $urgentAlertsCount = \Illuminate\Support\Facades\DB::connection('mongodb')
                ->getCollection('stock_alerts')
                ->countDocuments([
                    'business_id' => 'BUS-CD-SOUV-001',
                    'status' => 'ACTIVE',
                    'priority' => ['$in' => ['HIGH', 'CRITICAL']],
                ]);
        } catch (\Throwable $e) {
            $urgentAlertsCount = 0;
        }

        // Usuario actual (stub hasta que Eq. 1 tenga su API)
        $currentUser = $request->user();
        $userData = $currentUser ? [
            'name' => $currentUser->name ?? 'Administrador de inventario',
            'email' => $currentUser->email ?? 'admin@campus-digital.mx',
            'initials' => 'E4',
            'avatar_url' => null,
        ] : [
            'name' => 'Administrador de inventario',
            'email' => 'admin@campus-digital.mx',
            'initials' => 'E4',
            'avatar_url' => null,
        ];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $userData,
            ],
            'alertsCount' => $urgentAlertsCount,
            'csrfToken' => csrf_token(),
        ];
    }
}

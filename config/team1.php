<?php

return [
    'stub_enabled' => env('TEAM1_STUB_ENABLED', true),
    'default_user_id' => env('TEAM1_DEFAULT_USER_ID', 'USR-ADMIN-001'),
    'api_url' => env('TEAM1_API_URL', ''),
    'api_secret' => env('TEAM1_SERVICE_SECRET', ''),
    'api_timeout' => (int) env('TEAM1_TIMEOUT', 5),
    'cache_ttl' => (int) env('TEAM1_CACHE_TTL', 60),

    'allowed_roles' => [
        'admin',
        'inventory_manager',
        'buyer',
        'auditor',
    ],

    'users' => [
        'USR-ADMIN-001' => ['name' => 'Jose Arreguin', 'email' => '23030824@itcelaya.edu.mx', 'role' => 'admin'],
        'USR-INV-001' => ['name' => 'Ana Martinez', 'email' => 'ana.inv@campus.mx', 'role' => 'inventory_manager'],
        'USR-BUY-001' => ['name' => 'Luis Perez', 'email' => 'luis.buy@campus.mx', 'role' => 'buyer'],
        'USR-AUD-001' => ['name' => 'Sofia Lopez', 'email' => 'sofia.aud@campus.mx', 'role' => 'auditor'],
        'USR-STU-001' => ['name' => 'Alumno Ejemplo', 'email' => 'alumno@campus.mx', 'role' => 'alumno'],
    ],
];

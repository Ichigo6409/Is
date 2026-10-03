<?php

return [
    'stub_enabled' => env('TEAM1_STUB_ENABLED', true),
    'default_user_id' => env('TEAM1_DEFAULT_USER_ID', 'USR-ADMIN-001'),
    'api_url' => env('TEAM1_API_URL', ''),
    'api_secret' => env('TEAM1_SERVICE_SECRET', ''),
    'api_timeout' => (int) env('TEAM1_TIMEOUT', 5),
    'cache_ttl' => (int) env('TEAM1_CACHE_TTL', 60),

    // NUEVO: default 300s — debe coincidir con Eq. 1
    'allowed_clock_skew' => (int) env('TEAM1_ALLOWED_CLOCK_SKEW', 300),

    // NUEVO: si Eq. 1 no expone 'scope', asumimos este
    'role_scope_default' => env('TEAM1_ROLE_SCOPE_DEFAULT', 'global'),

    // NUEVO: roles que SIEMPRE son globales (ignoran scope del Eq. 1)
    'global_roles'       => ['admin', 'auditor'],

    /*
    |--------------------------------------------------------------------------
    | Roles autorizados en el Eq. 4
    |--------------------------------------------------------------------------
    | Roles canonicos que el Eq. 4 acepta. Cualquier rol fuera de esta lista
    | se considera no autorizado.
    */
    'allowed_roles' => [
        'admin',
        'inventory_manager',
        'buyer',
        'auditor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Diccionario de mapeo de roles (Eq. 1 -> Eq. 4)
    |--------------------------------------------------------------------------
    | Cuando el Eq. 1 exponga sus roles, van a venir con su propia nomenclatura.
    | Este diccionario mapea CUALQUIER variante al rol canonico del Eq. 4.
    |
    | Ejemplo: si el Eq. 1 devuelve "ADMIN_GENERAL" o "Administrador" o
    | "superadmin", todos se mapean a 'admin'.
    */
    'role_map' => [

        'admin' => [
            'admin', 'administrador', 'ADMIN', 'ADMINISTRADOR',
            'ADMIN_GENERAL', 'admin_general', 'SUPERADMIN', 'superadmin',
            'ADMINISTRATOR', 'administrator',
        ],

        'inventory_manager' => [
            'inventory_manager', 'inventory-manager', 'INVENTORY_MANAGER',
            'gestor_inventario', 'GESTOR_INVENTARIO', 'responsable_inventario',
            'RESPONSABLE_INVENTARIO', 'inventory', 'INVENTORY',
            'almacenista', 'ALMACENISTA',
        ],

        'buyer' => [
            'buyer', 'BUYER', 'comprador', 'COMPRADOR',
            'purchasing', 'PURCHASING', 'purchasing_manager',
            'PURCHASING_MANAGER', 'abastecedor', 'ABASTECEDOR',
        ],

        'auditor' => [
            'auditor', 'AUDITOR', 'reviewer', 'REVIEWER',
            'auditoria', 'AUDITORIA', 'auditor_lectura',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Usuarios stub (modo dev)
    |--------------------------------------------------------------------------
    */
    'users' => [
        'USR-ADMIN-001' => ['name' => 'Jose Arreguin', 'email' => '23030824@itcelaya.edu.mx', 'role' => 'admin'],
        'USR-INV-001' => ['name' => 'Ana Martinez', 'email' => 'ana.inv@campus.mx', 'role' => 'inventory_manager'],
        'USR-BUY-001' => ['name' => 'Luis Perez', 'email' => 'luis.buy@campus.mx', 'role' => 'buyer'],
        'USR-AUD-001' => ['name' => 'Sofia Lopez', 'email' => 'sofia.aud@campus.mx', 'role' => 'auditor'],
        'USR-STU-001' => ['name' => 'Alumno Ejemplo', 'email' => 'alumno@campus.mx', 'role' => 'alumno'],
    ],
];

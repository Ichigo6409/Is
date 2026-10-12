<?php

/**
 * Configuracion de integracion con Equipo 1 (Identidad).
 *
 * Contrato real (INT-1B.5): OAuth2 client_credentials + JWT Bearer.
 * No hay endpoints /users/*, no hay HMAC.
 *
 * Decision de mapeo (Opcion B):
 *   - Un usuario puede tener VARIOS roles business en Eq.1.
 *   - Eq.4 devuelve 'roles' como array y evalua la UNION de permisos.
 *   - 'role' se mantiene como el primario (mayor prioridad) solo para UI.
 *
 * Mapeo 1:1 de Eq.1 -> Eq.4:
 *   business_owner    -> admin
 *   business_manager  -> admin
 *   inventory_manager -> inventory_manager
 *   buyer             -> buyer
 *   cashier           -> (ignorado, fuera de dominio Eq.4)
 *   auditor           -> no existe en Eq.1, solo modo stub
 */

return [
    // ----------------------------------------------------------
    // Modo de operacion
    // ----------------------------------------------------------
    'stub_enabled'     => env('TEAM1_STUB_ENABLED', true),
    'default_user_id'  => env('TEAM1_DEFAULT_USER_ID', 'USR-ADMIN-001'),

    // ----------------------------------------------------------
    // OAuth2 client_credentials
    // ----------------------------------------------------------
    'oauth' => [
        'token_url'     => env('TEAM1_OAUTH_TOKEN_URL', ''),
        'api_url'       => env('TEAM1_API_URL', ''),
        'client_id'     => env('TEAM1_CLIENT_ID', ''),
        'client_secret' => env('TEAM1_CLIENT_SECRET', ''),
        'scopes'        => [
            'identity:authorization:check',
            'identity:assignments:read',
        ],
        'timeout'          => (int) env('TEAM1_TIMEOUT', 5),
        'token_cache_key'  => 'team1_oauth_token',
        'refresh_skew'     => 60,
    ],

    // ----------------------------------------------------------
    // Business scope
    // ----------------------------------------------------------
    'business_id' => env('TEAM4_BUSINESS_ID', 'BUS-CD-SOUV-001'),

    // ----------------------------------------------------------
    // Mapeo Eq.1 -> Eq.4 (1:1)
    // ----------------------------------------------------------
    // Si un usuario tiene varios roles Eq.1, Eq.4 devuelve la union
    // de los roles Eq.4 correspondientes. Ejemplo:
    //   Eq.1: [business_owner, buyer]
    //   Eq.4: ['roles' => ['admin', 'buyer'], 'role' => 'admin']
    'role_map_business' => [
        'business_owner'    => 'admin',
        'business_manager'  => 'admin',
        'inventory_manager' => 'inventory_manager',
        'buyer'             => 'buyer',
    ],

    // Roles Eq.4 autorizados (cualquiera de esta lista sirve)
    'allowed_roles' => ['admin', 'inventory_manager', 'buyer', 'auditor'],

    // ----------------------------------------------------------
    // Cache de respuestas
    // ----------------------------------------------------------
    'cache_ttl' => (int) env('TEAM1_CACHE_TTL', 60),

    // ----------------------------------------------------------
    // Usuarios stub (solo modo dev)
    // ----------------------------------------------------------
    'users' => [
        'USR-ADMIN-001' => ['name' => 'Jose Arreguin', 'email' => '23030824@itcelaya.edu.mx', 'role' => 'admin'],
        'USR-INV-001'   => ['name' => 'Ana Martinez',  'email' => 'ana.inv@campus.mx',        'role' => 'inventory_manager'],
        'USR-BUY-001'   => ['name' => 'Luis Perez',    'email' => 'luis.buy@campus.mx',       'role' => 'buyer'],
        'USR-AUD-001'   => ['name' => 'Sofia Lopez',   'email' => 'sofia.aud@campus.mx',      'role' => 'auditor'],
        'USR-MULTI-001' => ['name' => 'Marta Doble',   'email' => 'marta.doble@campus.mx',    'role' => 'admin', 'extra_roles' => ['inventory_manager']],
        'USR-MULTI2-001' => ['name' => 'Eva Doble', 'email' => 'eva.doble@campus.mx', 'role' => 'inventory_manager', 'extra_roles' => ['buyer']],
    ],
];
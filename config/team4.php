<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Negocio por defecto del Equipo 4
    |--------------------------------------------------------------------------
    | Cuando el Eq. 1 exponga usuarios con scope "business", se validara que
    | su role_business_id coincida con este valor. Mientras estamos en modo
    | stub (scope global), este valor solo se usa como identificador del
    | negocio actual para Consultas/KPIs.
    */
    'business_id' => env('TEAM4_BUSINESS_ID', 'BUS-CD-SOUV-001'),
];
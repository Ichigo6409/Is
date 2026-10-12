<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Servicio de Identidad de Eq.4.
 *
 * Contrato con Eq.1 (INT-1B.5): OAuth2 + /identity/assignments.
 * Eq.1 NO devuelve nombre ni email. Solo role_key + status + membership.
 *
 * Decision de mapeo (Opcion B): un usuario puede tener VARIOS roles
 * business en Eq.1. Este servicio:
 *   - Devuelve 'roles' como array canonico de Eq.4
 *   - 'role' se mantiene como el primario (mayor prioridad) para UI
 *   - userCan() evalua la UNION de permisos de todos los roles
 *
 * Mapeo 1:1: business_owner->admin, business_manager->admin,
 *            inventory_manager->inventory_manager, buyer->buyer.
 * 'auditor' no existe en Eq.1, solo funciona en modo stub.
 */
class IdentityService
{
    public function resolveCurrentUserId(): string
    {
        $fromSession = session('team4_impersonate_user_id');
        if (is_string($fromSession) && $fromSession !== '') {
            return $fromSession;
        }
        return (string) config('team1.default_user_id', 'USR-ADMIN-001');
    }

    public function getUser(string $userId): ?array
    {
        if ($userId === '') {
            return null;
        }

        $raw = config('team1.stub_enabled', true)
            ? $this->fetchFromStub($userId)
            : $this->fetchFromTeam1($userId);

        if ($raw === null) {
            return null;
        }

        return $this->normalizeUser($userId, $raw);
    }

    public function getRole(string $userId): ?string
    {
        return $this->getUser($userId)['role'] ?? null;
    }

    /**
     * Devuelve TODOS los roles canonicos del usuario (array).
     */
    public function getRoles(string $userId): array
    {
        return $this->getUser($userId)['roles'] ?? [];
    }

    public function getRoleScope(string $userId): ?array
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return null;
        }

        return [
            'name'        => $user['role'] ?? '',
            'scope'       => $user['role_scope'] ?? 'global',
            'business_id' => $user['role_business_id'] ?? null,
        ];
    }

    /**
     * Normaliza un rol crudo (Eq.1 o Eq.4) al canonico de Eq.4.
     *   - Si ya es canonico de Eq.4 -> lo devuelve igual.
     *   - Si es un rol business de Eq.1 -> lo mapea via role_map_business.
     *   - Desconocido -> null.
     */
    public function normalizeRole(string $rawRole): ?string
    {
        $raw = trim($rawRole);
        if ($raw === '') {
            return null;
        }

        // 1. Ya es canonico de Eq.4
        if (in_array($raw, (array) config('team1.allowed_roles', []), true)) {
            return $raw;
        }

        // 2. Es un rol business de Eq.1 -> mapear
        $map = (array) config('team1.role_map_business', []);
        if (isset($map[$raw])) {
            return $map[$raw];
        }

        return null;
    }

    /**
     * Devuelve true si al menos uno de los roles esta en allowed_roles.
     * Acepta string o array.
     */
    public function isAuthorized(string|array|null $roles): bool
    {
        if ($roles === null) {
            return false;
        }
        if (is_string($roles)) {
            $roles = $roles === '' ? [] : [$roles];
        }
        if (empty($roles)) {
            return false;
        }

        $allowed = (array) config('team1.allowed_roles', []);
        foreach ($roles as $r) {
            if (in_array($r, $allowed, true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Union de permisos de todos los roles.
     * Acepta string (un rol) o array (varios roles).
     */
    public function getPermissions(string|array|null $roles): array
    {
        if ($roles === null) {
            return [];
        }
        if (is_string($roles)) {
            $roles = $roles === '' ? [] : [$roles];
        }
        if (empty($roles)) {
            return [];
        }

        $catalog = (array) config('team4_permissions.roles', []);
        $union   = [];

        foreach ($roles as $role) {
            if (!isset($catalog[$role])) {
                continue;
            }
            $perms = (array) ($catalog[$role]['permissions'] ?? []);

            // Si alguno tiene '*', la union es '*'
            if (in_array('*', $perms, true)) {
                return ['*'];
            }
            $union = array_merge($union, $perms);
        }

        return array_values(array_unique($union));
    }

    /**
     * Evalua la union de permisos de todos los roles del usuario.
     */
    public function userCan(string $userId, string $permission): bool
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return false;
        }

        $perms = $this->getPermissions($user['roles'] ?? []);
        if (in_array('*', $perms, true)) {
            return true;
        }

        return in_array($permission, $perms, true);
    }

    public function getDashboardKpis(string|array|null $roles): array
    {
        if ($roles === null) {
            return [];
        }
        if (is_string($roles)) {
            $roles = $roles === '' ? [] : [$roles];
        }
        if (empty($roles)) {
            return [];
        }

        $catalog = (array) config('team4_permissions.dashboard_kpis', []);
        $union   = [];
        foreach ($roles as $role) {
            $kpis = (array) ($catalog[$role] ?? []);
            if (in_array('*', $kpis, true)) {
                return ['*'];
            }
            $union = array_merge($union, $kpis);
        }
        return array_values(array_unique($union));
    }

    public function getSharedUser(string $userId): ?array
    {
        $user = $this->getUser($userId);
        if ($user === null) {
            return null;
        }

        return [
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],        // primario (string)
            'roles'      => $user['roles'],       // array completo
            'role_raw'   => $user['role_raw'],
            'initials'   => $user['initials'],
            'role_scope' => $user['role_scope'],
        ];
    }

    // ==========================================================
    // STUB (dev)
    // ==========================================================

    private function fetchFromStub(string $userId): ?array
    {
        $users = (array) config('team1.users', []);
        if (!isset($users[$userId])) {
            return null;
        }

        $entry = (array) $users[$userId];

        // Soportar 'extra_roles' para probar multi-rol en dev
        $roles = [];
        if (!empty($entry['role'])) {
            $roles[] = $entry['role'];
        }
        foreach ((array) ($entry['extra_roles'] ?? []) as $extra) {
            if (!in_array($extra, $roles, true)) {
                $roles[] = $extra;
            }
        }

        return [
            'name'             => (string) ($entry['name'] ?? 'Usuario'),
            'email'            => (string) ($entry['email'] ?? ''),
            'roles'            => $roles,
            'role_scope'       => (string) ($entry['role_scope'] ?? 'global'),
            'role_business_id' => $entry['role_business_id'] ?? null,
        ];
    }

    // ==========================================================
    // REAL (Eq.1 OAuth2)
    // ==========================================================

    private function fetchFromTeam1(string $userId): ?array
    {
        $cacheKey = "team1_user_{$userId}";
        $ttl      = (int) config('team1.cache_ttl', 60);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $client = Team1OAuthClient::make();
        if (!$client->isConfigured()) {
            Log::warning('Team1OAuthClient no configurado; no se puede validar usuario.', [
                'user_id' => $userId,
            ]);
            return null;
        }

        $businessId = (string) config('team1.business_id', 'BUS-CD-SOUV-001');

        $assignments = $client->assignments($userId, 'business', $businessId);

        if ($assignments === null) {
            Log::warning('Eq.1 no disponible para assignments (o el subject no existe).', [
                'user_id' => $userId,
            ]);
            return null;
        }

        // Solo membership 'active' autoriza.
        $status = (string) ($assignments['membership_status'] ?? 'unknown');
        if ($status !== 'active') {
            Log::info('Eq.1 reporta membership no-active; se rechaza.', [
                'user_id' => $userId,
                'status'  => $status,
            ]);
            return null;
        }

        // Extraer role_key de cada role activo
        $eq1Roles = [];
        foreach ((array) ($assignments['roles'] ?? []) as $roleRow) {
            $roleRow = (array) $roleRow;
            if (($roleRow['status'] ?? '') === 'active' && !empty($roleRow['role_key'])) {
                $eq1Roles[] = (string) $roleRow['role_key'];
            }
        }

        if (empty($eq1Roles)) {
            Log::info('Eq.1 no emitio ningun rol business activo para el subject.', [
                'user_id' => $userId,
            ]);
            return null;
        }

        // Mapear Eq.1 -> Eq.4 (los valores de role_map_business son canonicos Eq.4)
        $map = (array) config('team1.role_map_business', []);
        $canonical = [];
        foreach ($eq1Roles as $eq1Role) {
            $eq4Role = $map[$eq1Role] ?? null;
            if ($eq4Role !== null && !in_array($eq4Role, $canonical, true)) {
                $canonical[] = $eq4Role;
            }
        }

        if (empty($canonical)) {
            Log::info('Eq.1 emite roles que no mapean a Eq.4.', [
                'user_id'    => $userId,
                'eq1_roles'  => $eq1Roles,
            ]);
            return null;
        }

        // Eq.1 no da nombre ni email; el panel mostrara solo el subject_id.
        $raw = [
            'name'             => '',  // Eq.1 minimiza datos
            'email'            => '',
            'roles'            => $canonical,   // ya son canonicos Eq.4
            'role_scope'       => 'business',
            'role_business_id' => $businessId,
            'subject_id'       => (string) ($assignments['subject_id'] ?? $userId),
        ];

        Cache::put($cacheKey, $raw, $ttl);
        return $raw;
    }

    // ==========================================================
    // Normalizacion final
    // ==========================================================

    private function normalizeUser(string $userId, array $raw): array
    {
        // Soportar tanto 'roles' (array) como 'role' (string) por compat.
        $rawRoles = $raw['roles'] ?? null;
        if ($rawRoles === null) {
            $rawRoles = isset($raw['role']) ? [$raw['role']] : [];
        }
        if (!is_array($rawRoles)) {
            $rawRoles = [$rawRoles];
        }

        $canonical = [];
        foreach ($rawRoles as $r) {
            $c = $this->normalizeRole((string) $r);
            if ($c !== null && !in_array($c, $canonical, true)) {
                $canonical[] = $c;
            }
        }

        // Filtrar a allowed_roles
        $allowed = (array) config('team1.allowed_roles', []);
        $canonical = array_values(array_filter(
            $canonical,
            fn ($r) => in_array($r, $allowed, true)
        ));

        // Rol primario = el primero del array (mayor prioridad segun role_map)
        $primary = $canonical[0] ?? '';

        // Scope
        $scope = $raw['role_scope'] ?? null;
        if ($scope === null) {
            $globalRoles = (array) config('team1.global_roles', ['admin']);
            $scope = in_array($primary, $globalRoles, true) ? 'global' : 'business';
        }

        $name = (string) ($raw['name'] ?? '');
        $display = $name !== '' ? $name : ('Usuario ' . substr($userId, 0, 8));

        return [
            'id'                => $userId,
            'name'              => $display,
            'email'             => (string) ($raw['email'] ?? ''),
            'roles'             => $canonical,
            'role'              => $primary,
            'role_raw'          => implode(', ', array_map('strval', $rawRoles)),
            'role_scope'        => (string) $scope,
            'role_business_id'  => $raw['role_business_id'] ?? null,
            'initials'          => $this->initialsFrom($display),
        ];
    }

    private function initialsFrom(string $name): string
    {
        $parts = array_filter(preg_split('/\s+/u', trim($name)) ?: []);
        if (count($parts) === 0) {
            return 'U';
        }
        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
}
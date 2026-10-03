<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class IdentityService
{
    public function resolveCurrentUserId(): string
    {
        $fromSession = session('team4_impersonate_user_id');
        if (is_string($fromSession) && $fromSession !== '') return $fromSession;
        return (string) config('team1.default_user_id', 'USR-ADMIN-001');
    }

    public function getUser(string $userId): ?array
    {
        if ($userId === '') return null;

        $raw = config('team1.stub_enabled', true)
            ? $this->fetchFromStub($userId)
            : $this->fetchFromTeam1($userId);

        if ($raw === null) return null;

        return $this->normalizeUser($userId, $raw);
    }

    public function getRole(string $userId): ?string
    {
        return $this->getUser($userId)['role'] ?? null;
    }

    public function getRoleScope(string $userId): ?array
    {
        $user = $this->getUser($userId);
        if ($user === null) return null;

        return [
            'name'        => $user['role'] ?? '',
            'scope'       => $user['role_scope'] ?? 'global',
            'business_id' => $user['role_business_id'] ?? null,
        ];
    }

    public function normalizeRole(string $rawRole): ?string
    {
        $raw = trim($rawRole);
        if ($raw === '') return null;

        $map = (array) config('team1.role_map', []);
        foreach ($map as $canonical => $aliases) {
            if (in_array($raw, (array) $aliases, true)) return $canonical;
        }

        $rawLower = mb_strtolower($raw);
        foreach ($map as $canonical => $aliases) {
            foreach ((array) $aliases as $alias) {
                if (mb_strtolower($alias) === $rawLower) return $canonical;
            }
        }

        return null;
    }

    public function isAuthorized(?string $role): bool
    {
        if ($role === null || $role === '') return false;
        return in_array($role, (array) config('team1.allowed_roles', []), true);
    }

    public function getPermissions(?string $role): array
    {
        if ($role === null || $role === '') return [];
        $roles = (array) config('team4_permissions.roles', []);
        if (!isset($roles[$role])) return [];
        return (array) ($roles[$role]['permissions'] ?? []);
    }

    public function userCan(string $userId, string $permission): bool
    {
        $role = $this->getRole($userId);
        if ($role === null) return false;

        $permissions = $this->getPermissions($role);
        if (in_array('*', $permissions, true)) return true;

        return in_array($permission, $permissions, true);
    }

    public function getDashboardKpis(?string $role): array
    {
        if ($role === null || $role === '') return [];
        $kpis = (array) config('team4_permissions.dashboard_kpis', []);
        return (array) ($kpis[$role] ?? []);
    }

    public function getSharedUser(string $userId): ?array
    {
        $user = $this->getUser($userId);
        if ($user === null) return null;

        return [
            'id'         => $user['id'],
            'name'       => $user['name'],
            'email'      => $user['email'],
            'role'       => $user['role'],
            'role_raw'   => $user['role_raw'],
            'initials'   => $user['initials'],
            'role_scope' => $user['role_scope'],
        ];
    }

    private function fetchFromStub(string $userId): ?array
    {
        $users = (array) config('team1.users', []);
        return $users[$userId] ?? null;
    }

    /**
     * Consulta Eq.1. Distingue 401/403/404/5xx y valida status.
     * Solo cachea respuestas validas.
     */
    private function fetchFromTeam1(string $userId): ?array
    {
        $cacheKey = "team1_user_{$userId}";
        $ttl = (int) config('team1.cache_ttl', 60);

        $cached = Cache::get($cacheKey);
        if ($cached !== null) return $cached;

        $client = Team1Client::make();
        if (!$client->isConfigured()) {
            Log::warning('Team1Client no configurado, no se puede validar usuario.', ['user_id' => $userId]);
            return null;
        }

        $response = $client->getUserRaw($userId);

        // null = 5xx tras reintentos o error de red
        if ($response === null) {
            Log::warning('Eq.1 no disponible tras reintentos.', ['user_id' => $userId]);
            return null;
        }

        // 401 = problema de integracion (secret invalido)
        if ($response->status() === 401) {
            Log::error('Eq.1 rechazo firma de integracion (401). Revisar TEAM1_SERVICE_SECRET.', [
                'user_id' => $userId,
            ]);
            return null;
        }

        // 403 = prohibido
        if ($response->status() === 403) {
            Log::info('Eq.1 reporta 403 prohibido.', ['user_id' => $userId]);
            return null;
        }

        // 404 = usuario inexistente
        if ($response->status() === 404) {
            Log::info('Eq.1 reporta usuario inexistente.', ['user_id' => $userId]);
            return null;
        }

        // Otros 4xx = bug de payload
        if ($response->clientError()) {
            Log::warning('Eq.1 respondio 4xx inesperado.', [
                'user_id' => $userId,
                'status'  => $response->status(),
            ]);
            return null;
        }

        // 5xx tras retries agotados
        if (!$response->successful()) {
            Log::warning('Eq.1 no disponible.', [
                'user_id' => $userId,
                'status'  => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();

        // Usuario inactivo por campo "active"
        if (array_key_exists('active', $data) && $data['active'] === false) {
            Log::info('Eq.1 reporta usuario inactivo (active=false).', ['user_id' => $userId]);
            return null;
        }

        $roleRaw  = $data['role']['name']  ?? $data['role'] ?? '';
        $scope    = $data['role']['scope'] ?? config('team1.role_scope_default', 'global');
        $business = $data['role']['business_id'] ?? null;
        $status   = $data['status'] ?? $data['role']['status'] ?? 'active';

        // Solo status 'active' autoriza
        $activeStatuses = ['active', 'activo', 'ACTIVE', 'ACTIVO'];
        if (!in_array($status, $activeStatuses, true)) {
            Log::info('Eq.1 reporta status no-activo, se rechaza.', [
                'user_id' => $userId,
                'status'  => $status,
            ]);
            return null;
        }

        $normalized = [
            'name'              => (string) ($data['name'] ?? 'Usuario'),
            'email'             => (string) ($data['email'] ?? ''),
            'role'              => (string) $roleRaw,
            'role_scope'        => (string) $scope,
            'role_business_id'  => $business,
        ];

        Cache::put($cacheKey, $normalized, $ttl);
        return $normalized;
    }

    private function normalizeUser(string $userId, array $raw): array
    {
        $rawRole       = (string) ($raw['role'] ?? '');
        $canonicalRole = $this->normalizeRole($rawRole);

        $scope = $raw['role_scope'] ?? null;
        if ($scope === null) {
            $globalRoles = (array) config('team1.global_roles', ['admin', 'auditor']);
            $scope = in_array($canonicalRole, $globalRoles, true)
                ? 'global'
                : config('team1.role_scope_default', 'global');
        }

        return [
            'id'                => $userId,
            'name'              => (string) ($raw['name'] ?? 'Usuario'),
            'email'             => (string) ($raw['email'] ?? ''),
            'role'              => (string) ($canonicalRole ?? ''),
            'role_raw'          => $rawRole,
            'role_scope'        => (string) $scope,
            'role_business_id'  => $raw['role_business_id'] ?? null,
            'initials'          => $this->initialsFrom((string) ($raw['name'] ?? 'U')),
        ];
    }

    private function initialsFrom(string $name): string
    {
        $parts = array_filter(preg_split('/\s+/u', trim($name)) ?: []);
        if (count($parts) === 0) return 'U';
        $first = mb_substr($parts[0], 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
}
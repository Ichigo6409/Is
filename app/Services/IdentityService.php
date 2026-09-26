<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
        if (config('team1.stub_enabled', true)) return $this->fetchFromStub($userId);
        return $this->fetchFromTeam1($userId);
    }

    public function getRole(string $userId): ?string
    {
        $user = $this->getUser($userId);
        return $user['role'] ?? null;
    }

    public function isAuthorized(?string $role): bool
    {
        if ($role === null || $role === '') return false;
        $allowed = (array) config('team1.allowed_roles', []);
        return in_array($role, $allowed, true);
    }

    public function getSharedUser(string $userId): ?array
    {
        $user = $this->getUser($userId);
        if ($user === null) return null;

        return [
            'id' => $userId,
            'name' => (string) ($user['name'] ?? 'Usuario'),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
            'initials' => $this->initialsFrom((string) ($user['name'] ?? 'U')),
        ];
    }

    private function fetchFromStub(string $userId): ?array
    {
        $users = (array) config('team1.users', []);
        return $users[$userId] ?? null;
    }

    private function fetchFromTeam1(string $userId): ?array
    {
        $cacheKey = "team1_user_{$userId}";
        $ttl = (int) config('team1.cache_ttl', 60);

        return Cache::remember($cacheKey, $ttl, function () use ($userId) {
            $baseUrl = rtrim((string) config('team1.api_url'), '/');
            if ($baseUrl === '') { Log::warning('TEAM1_API_URL no configurada.'); return null; }

            try {
                $response = Http::timeout((int) config('team1.api_timeout', 5))
                    ->withHeaders(['Authorization' => 'Bearer ' . (string) config('team1.api_secret')])
                    ->get("{$baseUrl}/users/{$userId}");

                if (!$response->successful()) return null;
                $data = $response->json();
                return [
                    'name' => (string) ($data['name'] ?? 'Usuario'),
                    'email' => (string) ($data['email'] ?? ''),
                    'role' => (string) ($data['role']['name'] ?? $data['role'] ?? ''),
                ];
            } catch (\Throwable $e) {
                Log::warning('Fallo al consultar Eq. 1', ['user_id' => $userId, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    private function initialsFrom(string $name): string
    {
        $parts = array_filter(preg_split('/\s+/u', trim($name)) ?: []);
        if (count($parts) === 0) return 'U';
        $first = mb_substr($parts[0], 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last);
    }
}

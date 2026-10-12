<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP hacia Equipo 1 (Identidad) - contrato INT-1B.5.
 *
 * OAuth2 client_credentials + JWT Bearer. Token cacheado hasta
 * expires_in - refresh_skew. Retry automatico en 401 (invalida + reintenta).
 *
 * NO hay HMAC. NO hay /users/{id}. NO devuelve emails ni nombres.
 */
class Team1OAuthClient
{
    public function __construct(
        protected string $tokenUrl,
        protected string $apiUrl,
        protected string $clientId,
        protected string $clientSecret,
        protected array  $scopes = [],
        protected int    $timeout = 5,
        protected string $cacheKey = 'team1_oauth_token',
        protected int    $refreshSkew = 60,
    ) {}

    public static function make(): self
    {
        $cfg = config('team1.oauth', []);
        return new self(
            tokenUrl:     (string) ($cfg['token_url'] ?? ''),
            apiUrl:       rtrim((string) ($cfg['api_url'] ?? ''), '/'),
            clientId:     (string) ($cfg['client_id'] ?? ''),
            clientSecret: (string) ($cfg['client_secret'] ?? ''),
            scopes:       (array)  ($cfg['scopes'] ?? []),
            timeout:      (int)    ($cfg['timeout'] ?? 5),
            cacheKey:     (string) ($cfg['token_cache_key'] ?? 'team1_oauth_token'),
            refreshSkew:  (int)    ($cfg['refresh_skew'] ?? 60),
        );
    }

    public function isConfigured(): bool
    {
        return $this->tokenUrl !== ''
            && $this->apiUrl !== ''
            && $this->clientId !== ''
            && $this->clientSecret !== '';
    }

    public function authorizationCheck(string $subjectId, string $capability, string $scopeType, string $scopeId): ?bool
    {
        $response = $this->request('POST', '/identity/authorization/check', [
            'subject_id' => $subjectId,
            'capability' => $capability,
            'scope_type' => $scopeType,
            'scope_id'   => $scopeId,
        ]);

        if ($response === null || !$response->successful()) {
            return null;
        }
        return (bool) ($response->json('authorized') ?? false);
    }

    public function assignments(string $subjectId, string $scopeType, string $scopeId): ?array
    {
        $response = $this->request('GET', '/identity/assignments', [
            'subject_id' => $subjectId,
            'scope_type' => $scopeType,
            'scope_id'   => $scopeId,
        ]);

        if ($response === null || !$response->successful()) {
            return null;
        }
        return $response->json();
    }

    public function studentStatus(string $studentId): ?array
    {
        $response = $this->request('GET', "/students/{$studentId}/status");
        if ($response === null || !$response->successful()) {
            return null;
        }
        return $response->json('data');
    }

    protected function getAccessToken(): ?string
    {
        $cached = Cache::get($this->cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout($this->timeout)
                ->acceptJson()
                ->post($this->tokenUrl, [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => implode(' ', $this->scopes),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Team1OAuthClient: no se pudo pedir token', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$response->successful()) {
            Log::warning('Team1OAuthClient: token endpoint error', [
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
            return null;
        }

        $token     = (string) $response->json('access_token', '');
        $expiresIn = (int)    $response->json('expires_in', 3600);

        if ($token === '') {
            Log::warning('Team1OAuthClient: token vacio');
            return null;
        }

        $ttl = max(30, $expiresIn - $this->refreshSkew);
        Cache::put($this->cacheKey, $token, $ttl);

        return $token;
    }

    protected function invalidateToken(): void
    {
        Cache::forget($this->cacheKey);
    }

    protected function request(string $method, string $path, array $body = []): ?Response
    {
        $token = $this->getAccessToken();
        if ($token === null) {
            return null;
        }

        try {
            $response = $this->doRequest($method, $path, $body, $token);

            if ($response->status() === 401) {
                $this->invalidateToken();
                $token = $this->getAccessToken();
                if ($token === null) {
                    return null;
                }
                $response = $this->doRequest($method, $path, $body, $token);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::warning('Team1OAuthClient: error de red', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    protected function doRequest(string $method, string $path, array $body, string $token): Response
    {
        $url = $this->apiUrl . $path;
        $http = Http::timeout($this->timeout)
            ->withToken($token)
            ->acceptJson();

        if ($method === 'GET') {
            return $http->get($url, $body);
        }

        return $http->{$method}($url, $body);
    }
}
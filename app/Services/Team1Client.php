<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente HTTP hacia Eq.1 con firma HMAC-SHA256 y retry exponencial en 5xx.
 *
 * Payload de firma: timestamp | method | path | sha256(body)
 * Retry: 100ms -> 500ms -> 1500ms. Solo en 5xx o excepcion de red.
 * Los 4xx NO se reintentan.
 */
class Team1Client
{
    public function __construct(
        protected string $baseUrl,
        protected string $secret,
        protected int $timeout = 5,
        protected int $clockSkew = 300,
    ) {}

    public static function make(): self
    {
        return new self(
            baseUrl:   rtrim((string) config('team1.api_url'), '/'),
            secret:    (string) config('team1.api_secret', ''),
            timeout:   (int)    config('team1.api_timeout', 5),
            clockSkew: (int)    config('team1.allowed_clock_skew', 300),
        );
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl !== '' && $this->secret !== '';
    }

    public function getUser(string $userId): ?array
    {
        $r = $this->getUserRaw($userId);
        return $r?->successful() ? $r->json() : null;
    }

    public function getUserRole(string $userId): ?array
    {
        $r = $this->request('GET', "/users/{$userId}/role");
        return $r?->successful() ? $r->json() : null;
    }

    public function userExists(string $userId): ?array
    {
        $r = $this->request('GET', "/users/{$userId}/exists");
        return $r?->successful() ? $r->json() : null;
    }

    public function listRoles(): ?array
    {
        $r = $this->request('GET', '/roles');
        return $r?->successful() ? $r->json() : null;
    }

    /**
     * Devuelve la Response cruda (para diagnostico de codigos).
     */
    public function getUserRaw(string $userId): ?Response
    {
        return $this->request('GET', "/users/{$userId}");
    }

    /**
     * Request con firma HMAC y retry exponencial en 5xx / errores de red.
     * Devuelve null si tras los reintentos no hay respuesta o si el cliente no esta configurado.
     */
    protected function request(string $method, string $path, array $body = []): ?Response
    {
        if (!$this->isConfigured()) {
            Log::warning('Team1Client no configurado (falta api_url o api_secret).');
            return null;
        }

        $backoff = [100, 500, 1500];
        $lastException = null;

        for ($attempt = 0; $attempt < count($backoff); $attempt++) {
            try {
                $response = $this->doRequest($method, $path, $body);

                // 5xx -> reintentar (salvo en el ultimo intento)
                if ($response->serverError() && $attempt < count($backoff) - 1) {
                    Log::warning('Eq.1 respondio 5xx, reintentando', [
                        'path'    => $path,
                        'status'  => $response->status(),
                        'attempt' => $attempt + 1,
                        'next_ms' => $backoff[$attempt + 1],
                    ]);
                    usleep($backoff[$attempt + 1] * 1000);
                    continue;
                }

                return $response;
            } catch (\Throwable $e) {
                $lastException = $e;
                if ($attempt < count($backoff) - 1) {
                    usleep($backoff[$attempt + 1] * 1000);
                    continue;
                }
            }
        }

        Log::warning('Eq.1 no disponible tras reintentos', [
            'path'  => $path,
            'error' => $lastException?->getMessage(),
        ]);

        return null;
    }

    protected function doRequest(string $method, string $path, array $body = []): Response
    {
        $timestamp = (string) time();
        $rawBody   = empty($body) ? '' : json_encode($body);
        $bodyHash  = hash('sha256', $rawBody);
        $payload   = "{$timestamp}|{$method}|{$path}|{$bodyHash}";
        $signature = hash_hmac('sha256', $payload, $this->secret);

        $request = Http::timeout($this->timeout)
            ->withHeaders([
                'X-Team-Timestamp' => $timestamp,
                'X-Team-Signature' => $signature,
                'Accept'           => 'application/json',
                'Content-Type'     => 'application/json',
            ]);

        return $method === 'GET'
            ? $request->get("{$this->baseUrl}{$path}")
            : $request->send($method, "{$this->baseUrl}{$path}", ['json' => $body]);
    }
}
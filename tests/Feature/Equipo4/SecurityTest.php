<?php

namespace Tests\Feature\Equipo4;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    protected string $baseUrl = 'http://localhost';

    /** @test */
    public function test_hmac_invalido_retorna_401(): void
    {
        $r = Http::withHeaders([
            'X-Team-Timestamp' => (string) time(),
            'X-Team-Signature' => str_repeat('d', 64),
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
        ])->withBody('{"items":[]}', 'application/json')
          ->post($this->baseUrl . '/api/v1/inventory/availability');
        $this->assertSame(401, $r->status());
    }

    /** @test */
    public function test_ruta_e3_tambien_requiere_hmac(): void
    {
        $r = Http::withHeaders([
            'X-Team-Timestamp' => (string) time(),
            'X-Team-Signature' => str_repeat('e', 64),
            'Accept'           => 'application/json',
            'Content-Type'     => 'application/json',
        ])->withBody('{"items":[]}', 'application/json')
          ->post($this->baseUrl . '/api/equipo4/integration/availability');
        $this->assertSame(401, $r->status());
    }
}
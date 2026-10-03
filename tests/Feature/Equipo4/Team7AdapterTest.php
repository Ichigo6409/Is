<?php

namespace Tests\Feature\Equipo4;

use App\Services\Team7AdapterService;
use Tests\TestCase;

class Team7AdapterTest extends TestCase
{
    protected Team7AdapterService $adapter;
    protected string $sku = 'SOUV-TAZA-001';

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = app(Team7AdapterService::class);
    }

    /** @test */
    public function test_availability_por_sku_funciona(): void
    {
        $res = $this->adapter->availability([
            ['product_reference' => $this->sku, 'quantity' => 1],
        ]);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('available', $res);
        $this->assertArrayHasKey('items', $res);
    }

    /** @test */
    public function test_reserve_y_confirm_sin_payment_intent(): void
    {
        $reserve = $this->adapter->reserve([
            'origin_reference' => 'TEST-RWD-' . uniqid(),
            'origin_type'      => 'REWARD',
            'items'            => [['product_reference' => $this->sku, 'quantity' => 1]],
            'idempotency_key'  => 'test-7-' . uniqid(),
        ]);
        $this->assertSame('reserved', $reserve['status']);
        $confirm = $this->adapter->confirm($reserve['reservation_id'], [
            'delivered_by'    => 'TEST-STAFF',
            'idempotency_key' => 'test-7-confirm-' . uniqid(),
        ]);
        $this->assertSame('consumed', $confirm['status']);
        $this->assertArrayHasKey('stock_movement_id', $confirm);
    }

    /** @test */
    public function test_release_es_idempotente(): void
    {
        $reserve = $this->adapter->reserve([
            'origin_reference' => 'TEST-RWD-R-' . uniqid(),
            'origin_type'      => 'REWARD',
            'items'            => [['product_reference' => $this->sku, 'quantity' => 1]],
            'idempotency_key'  => 'test-7-rel-' . uniqid(),
        ]);
        $rid = $reserve['reservation_id'];
        $r1 = $this->adapter->release($rid, ['idempotency_key' => 'rel-key-1-' . $rid]);
        $this->assertSame('released', $r1['status']);
    }

    /** @test */
    public function test_status_publico_mapea_correctamente(): void
    {
        $reserve = $this->adapter->reserve([
            'origin_reference' => 'TEST-MAP-' . uniqid(),
            'origin_type'      => 'REWARD',
            'items'            => [['product_reference' => $this->sku, 'quantity' => 1]],
            'idempotency_key'  => 'test-map-' . uniqid(),
        ]);
        $rid = $reserve['reservation_id'];
        $this->assertSame('reserved', $this->adapter->show($rid)['status']);
        $this->adapter->confirm($rid, ['idempotency_key' => 'cf-' . $rid]);
        $this->assertSame('consumed', $this->adapter->show($rid)['status']);
    }
}
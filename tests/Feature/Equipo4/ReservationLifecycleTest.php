<?php

namespace Tests\Feature\Equipo4;

use App\Services\ReservationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationLifecycleTest extends TestCase
{
    protected string $businessId;
    protected string $testProductId;
    protected string $testLocationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->businessId = (string) config('team4.business_id');
        $inv = DB::connection('mongodb')->getCollection('inventories')
            ->findOne(['available' => ['$gt' => 10], 'business_id' => $this->businessId]);
        $this->assertNotNull($inv, 'Se requiere stock > 10');
        $inv = (array) $inv;
        $this->testProductId = (string) $inv['product_id'];
        $this->testLocationId = (string) $inv['location_id'];
    }

    protected function readInventory(): array
    {
        return (array) DB::connection('mongodb')->getCollection('inventories')->findOne([
            'business_id' => $this->businessId,
            'product_id'  => $this->testProductId,
            'location_id' => $this->testLocationId,
        ]);
    }

    /** @test */
    public function test_reserva_reduce_available_e_incrementa_reserved(): void
    {
        $before = $this->readInventory();
        $svc = app(ReservationService::class);
        $res = $svc->reserve([
            'product_id'  => $this->testProductId,
            'location_id' => $this->testLocationId,
            'quantity'    => 3,
        ]);
        $after = $this->readInventory();
        $this->assertSame((int)$before['available'] - 3, (int)$after['available']);
        $this->assertSame((int)$before['reserved'] + 3, (int)$after['reserved']);
        $svc->release((string) $res->_id, 'TEST_CLEANUP');
    }

    /** @test */
    public function test_release_devuelve_available(): void
    {
        $beforeAvail = (int) $this->readInventory()['available'];
        $svc = app(ReservationService::class);
        $res = $svc->reserve([
            'product_id'  => $this->testProductId,
            'location_id' => $this->testLocationId,
            'quantity'    => 2,
        ]);
        $svc->release((string) $res->_id, 'TEST_CLEANUP');
        $this->assertSame($beforeAvail, (int) $this->readInventory()['available']);
    }

    /** @test */
    public function test_inventario_insuficiente_lanza_domain_exception(): void
    {
        $this->expectException(\DomainException::class);
        app(ReservationService::class)->reserve([
            'product_id'  => $this->testProductId,
            'location_id' => $this->testLocationId,
            'quantity'    => 999999,
        ]);
    }
}
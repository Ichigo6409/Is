<?php

namespace Tests\Feature\Equipo4;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Tests\TestCase;

class E7ExpiryTest extends TestCase
{
    /** @test */
    public function test_e7_expire_libera_reservas_vencidas_y_publica_evento(): void
    {
        $rid = 'E7-TEST-' . bin2hex(random_bytes(4));

        DB::connection('mongodb')->getCollection('team3_api_reservations')->insertOne([
            '_id'              => new ObjectId(),
            'reservation_id'   => $rid,
            'origin_system'    => 'E7',
            'origin_type'      => 'REWARD',
            'origin_reference' => 'RWD-TEST-' . bin2hex(random_bytes(2)),
            'business_id'      => config('team4.business_id'),
            'status'           => 'RESERVED',
            'source'           => 'REWARD',
            'items'            => [['product_id' => 'X', 'variant_id' => null, 'location_id' => 'Y', 'quantity' => 1]],
            'expires_at'       => new UTCDateTime((time() - 60) * 1000),
            'created_at'       => new UTCDateTime(),
            'updated_at'       => new UTCDateTime(),
        ]);

        Artisan::call('e7:expire');

        $updated = (array) DB::connection('mongodb')->getCollection('team3_api_reservations')
            ->findOne(['reservation_id' => $rid]);
        $this->assertSame('RELEASED', $updated['status'] ?? null);
        $this->assertSame('EXPIRED', $updated['release_reason'] ?? null);

        $event = DB::connection('mongodb')->getCollection('integration_outbox')
            ->findOne(['topic' => 'inventory.reservation.expired', 'payload.reservation_id' => $rid]);
        $this->assertNotNull($event, 'Debe existir evento expired');

        DB::connection('mongodb')->getCollection('team3_api_reservations')->deleteOne(['reservation_id' => $rid]);
        DB::connection('mongodb')->getCollection('integration_outbox')->deleteMany(['payload.reservation_id' => $rid]);
    }
}
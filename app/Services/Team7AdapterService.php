<?php

namespace App\Services;

use App\Api\Team3ApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adaptador Equipo 7 -> motor comun de reservas (Team3ApiService).
 * Mapea nomenclatura E7 (product_reference, variant_sku, origin_type, etc.)
 * al motor ya existente. NO duplica ReservationService.
 */
class Team7AdapterService
{
    protected string $businessId;
    protected string $collection = 'team3_api_reservations';

    public function __construct(protected Team3ApiService $inner)
    {
        $this->businessId = (string) config('team4.business_id', 'BUS-CD-SOUV-001');
    }

    public function availability(array $items): array
    {
        $mapped = [];
        foreach ($items as $it) {
            $pid = $this->resolveProductId($it['product_reference'] ?? $it['product_id'] ?? null);
            if ($pid === null) throw new \InvalidArgumentException('product_reference no resuelto: ' . ($it['product_reference'] ?? '?'));
            $mapped[] = [
                'product_id'  => $pid,
                'variant_id'  => $this->resolveVariantId($it['variant_sku'] ?? null),
                'location_id' => $it['location_id'] ?? null,
                'quantity'    => (int) ($it['quantity'] ?? 1),
            ];
        }
        $res = $this->inner->availability($mapped);
        return [
            'available' => $res['available'],
            'items' => array_map(fn($it) => [
                'product_reference' => $it['product_id'],
                'variant_sku'       => $it['variant_id'],
                'requested'         => $it['requested'],
                'available'         => $it['available'],
                'has_enough'        => $it['has_enough'],
            ], $res['items']),
        ];
    }

    public function reserve(array $data): array
    {
        $items = [];
        foreach ($data['items'] as $it) {
            $pid = $this->resolveProductId($it['product_reference'] ?? $it['product_id'] ?? null);
            if ($pid === null) throw new \InvalidArgumentException('product_reference no resuelto: ' . ($it['product_reference'] ?? '?'));
            $items[] = [
                'product_id'  => $pid,
                'variant_id'  => $this->resolveVariantId($it['variant_sku'] ?? null),
                'location_id' => $it['location_id'] ?? null,
                'quantity'    => (int) ($it['quantity'] ?? 1),
            ];
        }
        $inner = $this->inner->reserve([
            'order_id'           => $data['origin_reference'] ?? ('E7-' . Str::ulid()),
            'items'              => $items,
            'idempotency_key'    => $data['idempotency_key'],
            'source'             => $this->mapSource($data['origin_type'] ?? 'REWARD'),
            'external_reference' => $data['origin_reference'] ?? '',
            'expires_at'         => $data['expires_at'] ?? null,
            'actor_id'           => $data['actor_id'] ?? 'TEAM7',
        ]);
        DB::connection('mongodb')->getCollection($this->collection)->updateOne(
            ['reservation_id' => $inner['reservation_id']],
            ['$set' => [
                'origin_system'    => 'E7',
                'origin_type'      => $data['origin_type'] ?? 'REWARD',
                'origin_reference' => $data['origin_reference'] ?? '',
            ]]
        );
        return [
            'reservation_id'   => $inner['reservation_id'],
            'status'           => 'reserved',
            'origin_reference' => $data['origin_reference'] ?? '',
            'items'            => $items,
            'expires_at'       => $inner['expires_at'],
        ];
    }

    public function confirm(string $reservationId, array $data): array
    {
        $inner = $this->inner->confirm($reservationId, [
            'idempotency_key' => $data['idempotency_key'],
            'actor_id'        => $data['delivered_by'] ?? 'TEAM7',
        ]);
        $movementId = null;
        $firstProductId = $inner['movements'][0]['product_id'] ?? null;
        if ($firstProductId) {
            $mov = DB::connection('mongodb')->getCollection('stock_movements')->findOne(
                ['product_id' => $firstProductId, 'type' => 'SALE', 'business_id' => $this->businessId],
                ['sort' => ['created_at' => -1]]
            );
            if ($mov) $movementId = (string) $mov['_id'];
        }
        return [
            'reservation_id'    => $reservationId,
            'status'            => 'consumed',
            'stock_movement_id' => $movementId,
            'confirmed_at'      => now()->toIso8601String(),
        ];
    }

    public function release(string $reservationId, array $data): array
    {
        $this->inner->release($reservationId, [
            'idempotency_key' => $data['idempotency_key'],
            'reason'          => $data['reason'] ?? 'E7_RELEASE',
        ]);
        return [
            'reservation_id' => $reservationId,
            'status'         => 'released',
            'released_at'    => now()->toIso8601String(),
        ];
    }

    public function show(string $reservationId): ?array
    {
        $doc = DB::connection('mongodb')->getCollection($this->collection)
            ->findOne(['reservation_id' => $reservationId, 'business_id' => $this->businessId]);
        if (!$doc) return null;
        $d = (array) $doc;
        return [
            'reservation_id'   => $d['reservation_id'],
            'status'           => $this->mapStatusForE7($d['status'] ?? 'RESERVED', $d['release_reason'] ?? null),
            'origin_reference' => $d['origin_reference'] ?? ($d['external_reference'] ?? ''),
            'items'            => $d['items'] ?? [],
            'expires_at'       => $d['expires_at'] ?? null,
        ];
    }

    private function mapSource(string $e7): string
    {
        return match (strtolower($e7)) {
            'reward_redemption','reward','recompensa' => 'REWARD',
            'canje','redemption'                       => 'CANJE',
            default                                    => 'REWARD',
        };
    }

    private function mapStatusForE7(string $internal, ?string $releaseReason): string
    {
        return match ($internal) {
            'RESERVED'  => 'reserved',
            'CONFIRMED' => 'consumed',
            'RELEASED'  => ($releaseReason === 'EXPIRED' ? 'expired' : 'released'),
            'REJECTED'  => 'rejected',
            default     => strtolower($internal),
        };
    }

    private function resolveProductId(?string $ref): ?string
    {
        if (!$ref) return null;
        if (preg_match('/^[a-f0-9]{24}$/i', $ref)) return strtolower($ref);
        $p = DB::connection('mongodb')->getCollection('products')->findOne(['sku' => $ref, 'business_id' => $this->businessId]);
        return $p ? (string) $p['_id'] : null;
    }

    private function resolveVariantId(?string $sku): ?string
    {
        if (!$sku) return null;
        if (preg_match('/^[a-f0-9]{24}$/i', $sku)) return strtolower($sku);
        $v = DB::connection('mongodb')->getCollection('product_variants')->findOne(['sku' => $sku, 'business_id' => $this->businessId]);
        return $v ? (string) $v['_id'] : null;
    }
}

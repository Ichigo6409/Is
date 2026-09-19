<?php
namespace App\Services;

use App\Models\StockReservation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IntegrationContractService
{
    public function reserveStock(array $data): StockReservation
    {
        $key = $data['idempotency_key'];
        $cacheKey = 'team4:idempotency:'.$key;

        if ($cached = Cache::get($cacheKey)) {
            return StockReservation::find($cached);
        }

        $reservation = StockReservation::create([
            'business_id' => $data['business_id'],
            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
            'location_id' => $data['location_id'],
            'quantity' => (int) $data['quantity'],
            'status' => 'RESERVED',
            'source' => $data['source'],
            'external_reference' => $data['external_reference'] ?? (string) Str::uuid(),
            'expires_at' => $data['expires_at'],
        ]);

        Cache::put($cacheKey, (string)$reservation->_id, now()->addMinutes(30));
        return $reservation;
    }
}

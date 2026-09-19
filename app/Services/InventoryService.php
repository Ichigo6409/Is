<?php
namespace App\Services;

use App\Models\Inventory;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InventoryService
{
    public function changeStock(array $data): Inventory
    {
        $qty = (int) $data['quantity'];
        if ($qty === 0) throw new \InvalidArgumentException('La cantidad no puede ser cero.');

        $inventory = Inventory::firstOrNew([
            'business_id' => $data['business_id'],
            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
            'location_id' => $data['location_id'],
        ]);

        $current = (int) ($inventory->on_hand ?? 0);
        $new = $current + $qty;
        if ($new < 0) throw new \DomainException('Existencia insuficiente.');

        $reserved = (int) ($inventory->reserved ?? 0);
        if ($reserved > $new) throw new \DomainException('La reserva excede la existencia.');

        $inventory->on_hand = $new;
        $inventory->reserved = $reserved;
        $inventory->available = $new - $reserved;
        $inventory->status = $inventory->available > 0 ? 'AVAILABLE' : 'OUT_OF_STOCK';
        $inventory->save();

        StockMovement::create([
            ...$data,
            'quantity' => $qty,
            'correlation_id' => (string) Str::uuid(),
        ]);

        return $inventory;
    }
}

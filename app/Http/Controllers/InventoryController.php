<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Location;
use App\Services\InventoryService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = 25;

        $productsMap = Product::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($p) => (string) $p->_id);

        $warehousesMap = Warehouse::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($w) => (string) $w->_id);

        $locationsMap = Location::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($l) => (string) $l->_id);

        $query = Inventory::where('business_id', $this->businessId);

        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';

            // Buscar productos que matcheen SKU o nombre (fix: get + loop en vez de pluck)
            $matchingProducts = Product::where('business_id', $this->businessId)
                ->where(function ($sub) use ($regex) {
                    $sub->where('sku', 'regex', $regex)
                        ->orWhere('name', 'regex', $regex);
                })
                ->get();

            $matchedProductIds = [];
            foreach ($matchingProducts as $mp) {
                $matchedProductIds[] = (string) $mp->_id;
            }

            if (count($matchedProductIds) === 0) {
                $query->where('_id', 'nonexistent-id-no-match');
            } else {
                $query->whereIn('product_id', $matchedProductIds);
            }
        }

        $inventory = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        $data = collect($inventory->items())->map(function ($inv) use ($productsMap, $warehousesMap, $locationsMap) {
            $product = $productsMap->get((string) $inv->product_id);
            $location = $locationsMap->get((string) $inv->location_id);
            $warehouse = $location ? $warehousesMap->get((string) $location->warehouse_id) : null;

            return [
                '_id' => (string) $inv->_id,
                'product_id' => (string) $inv->product_id,
                'sku' => $product ? (string) $product->sku : '(desconocido)',
                'product_name' => $product ? (string) $product->name : '(desconocido)',
                'warehouse_name' => $warehouse ? (string) $warehouse->name : '—',
                'location_name' => $location ? (string) $location->name : '—',
                'location_id' => (string) $inv->location_id,
                'on_hand' => (int) $inv->on_hand,
                'reserved' => (int) $inv->reserved,
                'available' => (int) $inv->available,
                'status' => (string) $inv->status,
            ];
        })->values()->all();

        $productsList = $productsMap->map(fn($p) => [
            '_id' => (string) $p->_id,
            'sku' => (string) $p->sku,
            'name' => (string) $p->name,
        ])->values()->all();

        return Inertia::render('Equipo4/Inventario', [
            'inventory' => $data,
            'productsList' => $productsList,
            'pagination' => [
                'current_page' => $inventory->currentPage(),
                'last_page' => $inventory->lastPage(),
                'per_page' => $inventory->perPage(),
                'total' => $inventory->total(),
                'from' => $inventory->firstItem() ?? 0,
                'to' => $inventory->lastItem() ?? 0,
            ],
            'filters' => ['q' => $q],
        ]);
    }

    public function adjust(Request $request, InventoryService $inventoryService): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_id' => 'required|string',
            'quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:500',
        ]);

        $inventory = Inventory::where('_id', $validated['inventory_id'])
            ->where('business_id', $this->businessId)
            ->first();

        if (!$inventory) {
            return redirect()->route('equipo4.inventario.index')
                ->withErrors(['error' => 'Registro de inventario no encontrado.']);
        }

        try {
            $inventoryService->changeStock([
                'business_id' => $this->businessId,
                'product_id' => (string) $inventory->product_id,
                'variant_id' => $inventory->variant_id,
                'location_id' => (string) $inventory->location_id,
                'quantity' => (int) $validated['quantity'],
                'type' => 'ADJUSTMENT',
                'reason' => 'Ajuste manual: ' . $validated['reason'],
                'external_reference' => null,
                'actor_id' => 'USR-ADMIN-001',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al ajustar inventario.', [
                'inventory_id' => $validated['inventory_id'],
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('equipo4.inventario.index')
                ->withErrors(['error' => 'No se pudo ajustar: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.inventario.index')
            ->with('success', 'Ajuste aplicado correctamente.');
    }
}

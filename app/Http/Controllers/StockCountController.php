<?php

namespace App\Http\Controllers;

use App\Models\StockCount;
use App\Models\StockCountItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Location;
use App\Services\InventoryService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\ObjectId;

class StockCountController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = 25;

        $query = StockCount::where('business_id', $this->businessId);

        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $query->where('folio', 'regex', $regex);
        }

        $counts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $warehousesMap = Warehouse::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($w) => (string) $w->_id);

        $data = collect($counts->items())->map(function ($c) use ($warehousesMap) {
            $wh = $warehousesMap->get((string) $c->warehouse_id);
            return [
                '_id' => (string) $c->_id,
                'folio' => (string) $c->folio,
                'warehouse_id' => (string) $c->warehouse_id,
                'warehouse_name' => $wh ? (string) $wh->name : '—',
                'status' => (string) $c->status,
                'started_by' => (string) ($c->started_by ?? '—'),
                'differences_count' => (int) ($c->differences_count ?? 0),
                'notes' => (string) ($c->notes ?? ''),
            ];
        })->values()->all();

        $warehousesList = $warehousesMap->map(fn($w) => [
            '_id' => (string) $w->_id,
            'name' => (string) $w->name,
            'code' => (string) $w->code,
        ])->values()->all();

        return Inertia::render('Equipo4/Conteos', [
            'counts' => $data,
            'warehousesList' => $warehousesList,
            'pagination' => [
                'current_page' => $counts->currentPage(),
                'last_page' => $counts->lastPage(),
                'per_page' => $counts->perPage(),
                'total' => $counts->total(),
                'from' => $counts->firstItem() ?? 0,
                'to' => $counts->lastItem() ?? 0,
            ],
            'filters' => ['q' => $q],
        ]);
    }

    private function nextFolio(): string
    {
        $last = StockCount::where('business_id', $this->businessId)
            ->orderBy('folio', 'desc')
            ->first();

        if (!$last || empty($last->folio)) {
            return 'CNT-00001';
        }

        $lastFolio = (string) $last->folio;
        $num = (int) preg_replace('/[^0-9]/', '', $lastFolio);
        $next = $num + 1;

        return 'CNT-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|string',
            'notes' => 'nullable|string|max:500',
        ]);

        $warehouse = Warehouse::where('_id', $validated['warehouse_id'])
            ->where('business_id', $this->businessId)
            ->first();

        if (!$warehouse) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Almacén no encontrado.']);
        }

        $locations = Location::where('warehouse_id', $validated['warehouse_id'])->get();
        $locationIds = [];
        foreach ($locations as $loc) {
            $locationIds[] = (string) $loc->_id;
        }

        if (count($locationIds) === 0) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'El almacén no tiene ubicaciones configuradas.']);
        }

        $inventoryItems = Inventory::where('business_id', $this->businessId)
            ->whereIn('location_id', $locationIds)
            ->get();

        if ($inventoryItems->isEmpty()) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'No hay existencias registradas en este almacén.']);
        }

        $folio = $this->nextFolio();

        $productsMap = Product::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($p) => (string) $p->_id);

        $stockCountObjectId = new ObjectId();

        // Crear cabecera con insertOne directo
        DB::connection('mongodb')->getCollection('stock_counts')->insertOne([
            '_id' => $stockCountObjectId,
            'business_id' => $this->businessId,
            'folio' => $folio,
            'warehouse_id' => (string) $validated['warehouse_id'],
            'location_id' => null,
            'status' => 'DRAFT',
            'started_by' => 'USR-ADMIN-001',
            'closed_by' => null,
            'notes' => (string) ($validated['notes'] ?? ''),
            'differences_count' => 0,
            'created_at' => now()->toDateTime(),
            'updated_at' => now()->toDateTime(),
        ]);

        $stockCountId = (string) $stockCountObjectId;

        // Crear items con insertMany
        $itemsToInsert = [];
        foreach ($inventoryItems as $inv) {
            $product = $productsMap->get((string) $inv->product_id);
            $itemsToInsert[] = [
                '_id' => new ObjectId(),
                'business_id' => $this->businessId,
                'stock_count_id' => $stockCountId,
                'product_id' => (string) $inv->product_id,
                'product_sku' => $product ? (string) $product->sku : '',
                'product_name' => $product ? (string) $product->name : '',
                'expected_qty' => (int) $inv->available,
                'counted_qty' => 0,
                'difference' => 0,
                'unit_cost' => 0,
                'notes' => '',
                'created_at' => now()->toDateTime(),
                'updated_at' => now()->toDateTime(),
            ];
        }

        if (!empty($itemsToInsert)) {
            DB::connection('mongodb')->getCollection('stock_count_items')->insertMany($itemsToInsert);
        }

        return redirect()->route('equipo4.conteos.index')
            ->with('success', 'Conteo ' . $folio . ' creado. Procede a capturar cantidades.');
    }

    public function show(string $id)
    {
        $stockCount = StockCount::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$stockCount) {
            return response()->json(['error' => 'Conteo no encontrado.'], 404);
        }

        $productsMap = Product::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($p) => (string) $p->_id);

        $items = StockCountItem::where('stock_count_id', $id)
            ->orderBy('product_sku', 'asc')
            ->get()
            ->map(function ($i) use ($productsMap) {
                $product = $productsMap->get((string) $i->product_id);
                return [
                    '_id' => (string) $i->_id,
                    'product_id' => (string) $i->product_id,
                    'product_sku' => (string) $i->product_sku,
                    'product_name' => (string) $i->product_name,
                    'expected_qty' => (int) $i->expected_qty,
                    'counted_qty' => (int) $i->counted_qty,
                    'difference' => (int) $i->difference,
                    'stock_max' => $product ? (int) $product->stock_max : 0,
                ];
            })->values()->all();

        return response()->json([
            '_id' => (string) $stockCount->_id,
            'folio' => (string) $stockCount->folio,
            'status' => (string) $stockCount->status,
            'notes' => (string) ($stockCount->notes ?? ''),
            'items' => $items,
        ]);
    }

    public function capture(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|string',
            'items.*.counted_qty' => 'required|integer|min:0|max:1000000',
        ]);

        $stockCount = StockCount::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$stockCount) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Conteo no encontrado.']);
        }

        if ($stockCount->status !== 'DRAFT') {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Solo se pueden capturar conteos en estado Borrador.']);
        }

        $itemsCollection = DB::connection('mongodb')->getCollection('stock_count_items');

        $differencesCount = 0;
        $updatedCount = 0;
        $errors = [];

        foreach ($validated['items'] as $input) {
            $itemId = (string) $input['item_id'];
            $counted = (int) $input['counted_qty'];

            try {
                $itemObjectId = new ObjectId($itemId);
            } catch (\Throwable $e) {
                $errors[] = 'ID inválido: ' . $itemId;
                continue;
            }

            // Buscar el item existente para obtener expected_qty
            $existingItem = $itemsCollection->findOne(['_id' => $itemObjectId]);

            if (!$existingItem) {
                continue;
            }

            $expected = (int) ($existingItem['expected_qty'] ?? 0);
            $difference = $counted - $expected;

            // updateOne directo — bypasea el bug de save() en v5
            $itemsCollection->updateOne(
                ['_id' => $itemObjectId],
                ['$set' => [
                    'counted_qty' => $counted,
                    'difference' => $difference,
                    'updated_at' => now()->toDateTime(),
                ]]
            );

            if ($difference !== 0) {
                $differencesCount++;
            }
            $updatedCount++;
        }

        // Actualizar differences_count del StockCount
        try {
            DB::connection('mongodb')->getCollection('stock_counts')->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => [
                    'differences_count' => $differencesCount,
                    'updated_at' => now()->toDateTime(),
                ]]
            );
        } catch (\Throwable $e) {
            Log::error('Fallo al actualizar differences_count.', ['error' => $e->getMessage()]);
        }

        return redirect()->route('equipo4.conteos.index')
            ->with('success', 'Conteo ' . $stockCount->folio . ' actualizado. Items: ' . $updatedCount . '. Diferencias: ' . $differencesCount);
    }

    public function close(string $id, InventoryService $inventoryService): RedirectResponse
    {
        $stockCount = StockCount::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$stockCount) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Conteo no encontrado.']);
        }

        if ($stockCount->status !== 'DRAFT') {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Solo se pueden cerrar conteos en estado Borrador.']);
        }

        $items = StockCountItem::where('stock_count_id', $id)->get();

        if ($items->isEmpty()) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'El conteo no tiene items para ajustar.']);
        }

        $appliedAdjustments = [];
        $errors = [];
        $applied = 0;

        foreach ($items as $item) {
            $difference = (int) $item->difference;

            if ($difference === 0) {
                continue;
            }

            $inv = Inventory::where('business_id', $this->businessId)
                ->where('product_id', (string) $item->product_id)
                ->first();

            if (!$inv) {
                $errors[] = 'Sin inventario para SKU ' . $item->product_sku;
                continue;
            }

            try {
                $inventoryService->changeStock([
                    'business_id' => $this->businessId,
                    'product_id' => (string) $inv->product_id,
                    'variant_id' => $inv->variant_id,
                    'location_id' => (string) $inv->location_id,
                    'quantity' => $difference,
                    'type' => 'ADJUSTMENT',
                    'reason' => 'Conteo físico ' . $stockCount->folio,
                    'external_reference' => $stockCount->folio,
                    'actor_id' => 'USR-ADMIN-001',
                ]);

                $appliedAdjustments[] = [
                    'product_id' => (string) $inv->product_id,
                    'location_id' => (string) $inv->location_id,
                    'variant_id' => $inv->variant_id,
                    'delta' => $difference,
                ];
                $applied++;
            } catch (\Throwable $e) {
                $errors[] = 'SKU ' . $item->product_sku . ': ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            foreach ($appliedAdjustments as $adj) {
                try {
                    $inventoryService->changeStock([
                        'business_id' => $this->businessId,
                        'product_id' => $adj['product_id'],
                        'variant_id' => $adj['variant_id'],
                        'location_id' => $adj['location_id'],
                        'quantity' => -$adj['delta'],
                        'type' => 'ADJUSTMENT',
                        'reason' => 'Reversion conteo fallido ' . $stockCount->folio,
                        'external_reference' => $stockCount->folio,
                        'actor_id' => 'SYSTEM',
                    ]);
                } catch (\Throwable $e) {
                    Log::critical('Fallo al compensar ajuste de conteo.', [
                        'product_id' => $adj['product_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Errores al aplicar ajustes: ' . implode(' | ', $errors)]);
        }

        // Actualizar status con updateOne directo
        try {
            DB::connection('mongodb')->getCollection('stock_counts')->updateOne(
                ['_id' => new ObjectId($id)],
                ['$set' => [
                    'status' => 'CLOSED',
                    'closed_by' => 'USR-ADMIN-001',
                    'updated_at' => now()->toDateTime(),
                ]]
            );
        } catch (\Throwable $e) {
            Log::error('Fallo al cerrar conteo.', ['error' => $e->getMessage()]);
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'No se pudo cerrar: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.conteos.index')
            ->with('success', 'Conteo ' . $stockCount->folio . ' cerrado. ' . $applied . ' ajustes aplicados.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $stockCount = StockCount::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$stockCount) {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Conteo no encontrado.']);
        }

        if ($stockCount->status !== 'DRAFT') {
            return redirect()->route('equipo4.conteos.index')
                ->withErrors(['error' => 'Solo se pueden eliminar conteos en estado Borrador.']);
        }

        DB::connection('mongodb')->getCollection('stock_count_items')
            ->deleteMany(['stock_count_id' => $id]);
        DB::connection('mongodb')->getCollection('stock_counts')
            ->deleteOne(['_id' => new ObjectId($id)]);

        return redirect()->route('equipo4.conteos.index')
            ->with('success', 'Conteo eliminado.');
    }
}

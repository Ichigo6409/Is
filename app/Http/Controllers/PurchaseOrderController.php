<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\GoodsReceipt;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\ObjectId;

class PurchaseOrderController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $showHistory = $request->boolean('history', false);
        $perPage = 25;

        $query = PurchaseOrder::where('business_id', $this->businessId);

        if (!$showHistory) {
            $query->whereIn('status', ['BORRADOR', 'SOLICITADA', 'AUTORIZADA']);
        }

        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $query->where('folio', 'regex', $regex);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $suppliersMap = Supplier::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($s) => (string) $s->_id);

        $ordersData = collect($orders->items())->map(function ($o) use ($suppliersMap) {
            $supplier = $suppliersMap->get((string) $o->supplier_id);

            // Formato de fecha segura (sin instanceof porque Mongo puede devolver array)
            $expectedAt = null;
            if ($o->expected_at) {
                try {
                    if (is_string($o->expected_at)) {
                        $expectedAt = substr($o->expected_at, 0, 10);
                    } else {
                        $expectedAt = \Illuminate\Support\Carbon::parse($o->expected_at)->format('Y-m-d');
                    }
                } catch (\Throwable $e) {
                    $expectedAt = null;
                }
            }

            return [
                '_id' => (string) $o->_id,
                'folio' => (string) $o->folio,
                'supplier_id' => (string) $o->supplier_id,
                'supplier_name' => $supplier ? (string) $supplier->legal_name : 'Desconocido',
                'status' => (string) $o->status,
                'expected_at' => $expectedAt,
                'notes' => (string) ($o->notes ?? ''),
                'total_estimated' => (float) ($o->total_estimated ?? 0),
                'items_count' => (int) ($o->items_count ?? 0),
                // NO enviamos created_at para evitar problemas con Mongo
            ];
        })->values()->all();

        $suppliersList = Supplier::where('business_id', $this->businessId)
            ->where('status', 'ACTIVO')
            ->orderBy('legal_name', 'asc')
            ->get()
            ->map(fn($s) => ['_id' => (string) $s->_id, 'legal_name' => (string) $s->legal_name, 'code' => (string) $s->code])
            ->values()->all();

        $productsList = Product::where('business_id', $this->businessId)
            ->where('active', true)
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn($p) => ['_id' => (string) $p->_id, 'sku' => (string) $p->sku, 'name' => (string) $p->name])
            ->values()->all();

        return Inertia::render('Equipo4/Compras', [
            'orders' => $ordersData,
            'suppliersList' => $suppliersList,
            'productsList' => $productsList,
            'showHistory' => $showHistory,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem() ?? 0,
                'to' => $orders->lastItem() ?? 0,
            ],
            'filters' => [
                'q' => $q,
                'history' => $showHistory ? '1' : '',
            ],
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $items = $validated['items'];
        unset($validated['items']);

        $folio = 'OC-' . str_pad((string) (PurchaseOrder::where('business_id', $this->businessId)->count() + 1), 5, '0', STR_PAD_LEFT);
        $total = collect($items)->sum(fn($i) => $i['quantity'] * $i['unit_cost']);

        $validated['business_id'] = $this->businessId;
        $validated['folio'] = $folio;
        $validated['requested_by'] = 'USR-ADMIN-001';
        $validated['total_estimated'] = $total;
        $validated['items_count'] = count($items);

        $order = null;
        $createdItemIds = [];

        try {
            $order = new PurchaseOrder();
            $order->_id = new ObjectId();
            $order->fill($validated);
            $order->save();

            $orderId = (string) $order->_id;

            if (empty($orderId) || $orderId === 'undefined' || $orderId === 'null') {
                throw new \RuntimeException('No se pudo obtener el _id de la OC.');
            }

            foreach ($items as $index => $item) {
                $product = Product::where('_id', $item['product_id'])->first();

                $orderItem = new PurchaseOrderItem();
                $orderItem->_id = new ObjectId();
                $orderItem->fill([
                    'business_id' => $this->businessId,
                    'purchase_order_id' => $orderId,
                    'line' => $index + 1,
                    'product_id' => (string) $item['product_id'],
                    'product_sku' => $product ? (string) $product->sku : '',
                    'product_name' => $product ? (string) $product->name : '',
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => (float) $item['unit_cost'],
                    'subtotal' => (float) ($item['quantity'] * $item['unit_cost']),
                ]);
                $orderItem->save();
                $createdItemIds[] = (string) $orderItem->_id;
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al crear OC. Iniciando compensacion.', [
                'folio' => $folio,
                'error' => $e->getMessage(),
            ]);

            if (!empty($createdItemIds)) {
                try {
                    PurchaseOrderItem::whereIn('_id', $createdItemIds)->delete();
                } catch (\Throwable $ex) {
                    Log::critical('Fallo al limpiar items.', ['error' => $ex->getMessage()]);
                }
            }
            if ($order !== null && $order->_id) {
                try { $order->delete(); } catch (\Throwable $ex) {
                    Log::critical('Fallo al limpiar OC.', ['error' => $ex->getMessage()]);
                }
            }

            return redirect()->route('equipo4.compras.index')->withErrors(['error' => 'No se pudo crear la OC: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.compras.index')->with('success', 'Orden de compra ' . $folio . ' creada exitosamente.');
    }

    public function update(UpdatePurchaseOrderRequest $request, string $id): RedirectResponse
    {
        $order = PurchaseOrder::where('_id', $id)->where('business_id', $this->businessId)->first();
        if (!$order) abort(404, 'Orden de compra no encontrada.');

        if (in_array($order->status, ['CANCELADA', 'RECIBIDA_TOTAL'])) {
            return redirect()->route('equipo4.compras.index')->withErrors(['error' => 'No se puede editar una orden en estado ' . $order->status . '.']);
        }

        $validated = $request->validated();
        $items = $validated['items'];
        unset($validated['items']);

        $total = collect($items)->sum(fn($i) => $i['quantity'] * $i['unit_cost']);
        $validated['total_estimated'] = $total;
        $validated['items_count'] = count($items);

        $oldItems = PurchaseOrderItem::where('purchase_order_id', $id)
            ->get()
            ->map(fn($i) => $i->toArray())
            ->toArray();

        try {
            $order->fill($validated);
            $order->save();

            PurchaseOrderItem::where('purchase_order_id', $id)->delete();

            foreach ($items as $index => $item) {
                $product = Product::where('_id', $item['product_id'])->first();

                $orderItem = new PurchaseOrderItem();
                $orderItem->_id = new ObjectId();
                $orderItem->fill([
                    'business_id' => $this->businessId,
                    'purchase_order_id' => $id,
                    'line' => $index + 1,
                    'product_id' => (string) $item['product_id'],
                    'product_sku' => $product ? (string) $product->sku : '',
                    'product_name' => $product ? (string) $product->name : '',
                    'quantity' => (int) $item['quantity'],
                    'unit_cost' => (float) $item['unit_cost'],
                    'subtotal' => (float) ($item['quantity'] * $item['unit_cost']),
                ]);
                $orderItem->save();
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al actualizar OC.', ['order_id' => $id, 'error' => $e->getMessage()]);

            try {
                PurchaseOrderItem::where('purchase_order_id', $id)->delete();
                foreach ($oldItems as $oldItem) {
                    $restored = new PurchaseOrderItem();
                    $restored->_id = new ObjectId();
                    unset($oldItem['_id']);
                    $restored->fill($oldItem);
                    $restored->save();
                }
            } catch (\Throwable $ex) {
                Log::critical('Fallo al restaurar items.', ['error' => $ex->getMessage()]);
            }

            return redirect()->route('equipo4.compras.index')->withErrors(['error' => 'No se pudo actualizar la OC: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.compras.index')->with('success', 'Orden de compra actualizada exitosamente.');
    }

    public function changeStatus(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:autorizar,cancelar',
        ]);

        $order = PurchaseOrder::where('_id', $id)->where('business_id', $this->businessId)->first();
        if (!$order) {
            return redirect()->route('equipo4.compras.index')->withErrors(['error' => 'Orden de compra no encontrada.']);
        }

        $action = $validated['action'];
        $currentStatus = $order->status;

        if (in_array($currentStatus, ['RECIBIDA_TOTAL', 'CANCELADA'])) {
            return redirect()->route('equipo4.compras.index')->withErrors([
                'error' => 'La OC ya esta en estado ' . $currentStatus . ' y no se puede modificar.'
            ]);
        }

        if ($action === 'autorizar') {
            if (!in_array($currentStatus, ['BORRADOR', 'SOLICITADA'])) {
                return redirect()->route('equipo4.compras.index')->withErrors([
                    'error' => 'Solo se pueden autorizar OCs en estado Borrador o Solicitada. Estado actual: ' . $currentStatus
                ]);
            }

            $order->status = 'AUTORIZADA';
            $order->authorized_by = 'USR-ADMIN-001';
            $order->save();

            return redirect()->route('equipo4.compras.index')->with('success', 'OC ' . $order->folio . ' autorizada.');
        }

        if ($action === 'cancelar') {
            $hasReceipts = GoodsReceipt::where('purchase_order_id', $id)->exists();
            if ($hasReceipts) {
                return redirect()->route('equipo4.compras.index')->withErrors([
                    'error' => 'No se puede cancelar: la OC ya tiene recepciones asociadas.'
                ]);
            }

            $order->status = 'CANCELADA';
            $order->save();

            return redirect()->route('equipo4.compras.index')->with('success', 'OC ' . $order->folio . ' cancelada.');
        }

        return redirect()->route('equipo4.compras.index');
    }

    public function show(string $id)
    {
        $order = PurchaseOrder::where('_id', $id)->where('business_id', $this->businessId)->first();
        if (!$order) abort(404);

        $items = PurchaseOrderItem::where('purchase_order_id', $id)
            ->orderBy('line', 'asc')
            ->get()
            ->map(fn($i) => [
                'purchase_order_item_id' => (string) $i->_id,
                'product_id' => (string) $i->product_id,
                'product_sku' => (string) $i->product_sku,
                'product_name' => (string) $i->product_name,
                'quantity' => (int) $i->quantity,
                'unit_cost' => (float) $i->unit_cost,
                'subtotal' => (float) $i->subtotal,
            ])->values()->all();

        // Formato seguro de expected_at
        $expectedAt = null;
        if ($order->expected_at) {
            try {
                if (is_string($order->expected_at)) {
                    $expectedAt = substr($order->expected_at, 0, 10);
                } else {
                    $expectedAt = \Illuminate\Support\Carbon::parse($order->expected_at)->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                $expectedAt = null;
            }
        }

        return response()->json([
            '_id' => (string) $order->_id,
            'folio' => (string) $order->folio,
            'supplier_id' => (string) $order->supplier_id,
            'status' => (string) $order->status,
            'expected_at' => $expectedAt,
            'notes' => (string) ($order->notes ?? ''),
            'total_estimated' => (float) ($order->total_estimated ?? 0),
            'items' => $items,
        ]);
    }

    public function destroy(string $id): RedirectResponse
    {
        $order = PurchaseOrder::where('_id', $id)->where('business_id', $this->businessId)->first();
        if (!$order) abort(404, 'Orden de compra no encontrada.');

        $hasReceipts = GoodsReceipt::where('purchase_order_id', $id)->exists();
        if ($hasReceipts) {
            return redirect()->route('equipo4.compras.index')->withErrors(['error' => 'No se puede eliminar: la OC tiene recepciones asociadas.']);
        }

        try {
            PurchaseOrderItem::where('purchase_order_id', $id)->delete();
            $order->delete();
        } catch (\Throwable $e) {
            Log::error('Fallo al eliminar OC.', ['order_id' => $id, 'error' => $e->getMessage()]);
            return redirect()->route('equipo4.compras.index')->withErrors(['error' => 'No se pudo eliminar la OC.']);
        }

        return redirect()->route('equipo4.compras.index')->with('success', 'Orden de compra eliminada exitosamente.');
    }
}

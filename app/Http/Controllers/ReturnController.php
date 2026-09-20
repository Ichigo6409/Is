<?php

namespace App\Http\Controllers;

use App\Models\SupplierReturn;
use App\Models\CustomerReturn;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Location;
use App\Models\Inventory;
use App\Services\ReturnService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = 25;

        $productsMap = Product::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($p) => (string) $p->_id);

        $suppliersMap = Supplier::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($s) => (string) $s->_id);

        $locationsMap = Location::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($l) => (string) $l->_id);

        $supplierQuery = SupplierReturn::where('business_id', $this->businessId);
        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $supplierQuery->where('folio', 'regex', $regex);
        }
        $supplierReturns = $supplierQuery->orderBy('created_at', 'desc')->limit(200)->get();

        $customerQuery = CustomerReturn::where('business_id', $this->businessId);
        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $customerQuery->where('folio', 'regex', $regex);
        }
        $customerReturns = $customerQuery->orderBy('created_at', 'desc')->limit(200)->get();

        $all = collect();

        foreach ($supplierReturns as $r) {
            $supplier = $suppliersMap->get((string) $r->supplier_id);
            $all->push([
                '_id' => (string) $r->_id,
                'folio' => (string) $r->folio,
                'type' => 'A proveedor',
                'reference' => (string) ($r->source_reference ?? ''),
                'reason' => (string) ($r->reason ?? ''),
                'resolution' => 'Devolución',
                'status' => (string) $r->status,
                'third_party' => $supplier ? (string) $supplier->legal_name : '—',
            ]);
        }

        foreach ($customerReturns as $r) {
            $all->push([
                '_id' => (string) $r->_id,
                'folio' => (string) $r->folio,
                'type' => 'De cliente',
                'reference' => (string) ($r->sale_reference ?? ''),
                'reason' => (string) ($r->reason ?? ''),
                'resolution' => (string) ($r->resolution ?? ''),
                'status' => (string) $r->status,
                'third_party' => (string) ($r->customer_id ?? '—'),
            ]);
        }

        $allData = $all->sortByDesc('folio')->values()->all();
        $total = count($allData);
        $page = max(1, (int) $request->query('page', 1));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $from = ($page - 1) * $perPage;
        $items = array_slice($allData, $from, $perPage);

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

        $locationsList = $locationsMap->map(fn($l) => [
            '_id' => (string) $l->_id,
            'name' => (string) $l->name,
            'code' => (string) $l->code,
        ])->values()->all();

        // NUEVO: mapa de qué ubicaciones tienen stock de cada producto.
        // Estructura: { product_id: { location_id: available_qty } }
        $inventoryMap = [];
        $allInventory = Inventory::where('business_id', $this->businessId)->get();
        foreach ($allInventory as $inv) {
            $pid = (string) $inv->product_id;
            $lid = (string) $inv->location_id;
            $qty = (int) $inv->available;
            if (!isset($inventoryMap[$pid])) {
                $inventoryMap[$pid] = [];
            }
            $inventoryMap[$pid][$lid] = $qty;
        }

        return Inertia::render('Equipo4/Devoluciones', [
            'returns' => $items,
            'suppliersList' => $suppliersList,
            'productsList' => $productsList,
            'locationsList' => $locationsList,
            'inventoryMap' => $inventoryMap,
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $from + 1 : 0,
                'to' => min($from + $perPage, $total),
            ],
            'filters' => ['q' => $q],
        ]);
    }

    public function storeSupplier(Request $request, ReturnService $returnService): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_id' => 'nullable|string',
            'product_id' => 'required|string',
            'location_id' => 'required|string',
            'quantity' => 'required|integer|min:1|max:100000',
            'reason' => 'required|string|max:500',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $returnService->supplier([
                'business_id' => $this->businessId,
                'product_id' => $validated['product_id'],
                'variant_id' => null,
                'location_id' => $validated['location_id'],
                'quantity' => (int) $validated['quantity'],
                'reason' => $validated['reason'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'actor_id' => 'USR-ADMIN-001',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al registrar devolucion a proveedor.', ['error' => $e->getMessage()]);
            return redirect()->route('equipo4.devoluciones.index')
                ->withErrors(['error' => 'No se pudo registrar: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.devoluciones.index')
            ->with('success', 'Devolución a proveedor registrada.');
    }

    public function storeCustomer(Request $request, ReturnService $returnService): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'location_id' => 'required|string',
            'quantity' => 'required|integer|min:1|max:100000',
            'resolution' => 'required|string|in:RESTOCK,QUARANTINE,REPLACE,REFUND',
            'reason' => 'required|string|max:500',
            'reference' => 'nullable|string|max:100',
        ]);

        try {
            $returnService->customer([
                'business_id' => $this->businessId,
                'product_id' => $validated['product_id'],
                'variant_id' => null,
                'location_id' => $validated['location_id'],
                'quantity' => (int) $validated['quantity'],
                'resolution' => $validated['resolution'],
                'reason' => $validated['reason'],
                'customer_id' => null,
                'customer_type' => 'ALUMNO',
                'reference' => $validated['reference'] ?? null,
                'actor_id' => 'USR-ADMIN-001',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al registrar devolucion de cliente.', ['error' => $e->getMessage()]);
            return redirect()->route('equipo4.devoluciones.index')
                ->withErrors(['error' => 'No se pudo registrar: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.devoluciones.index')
            ->with('success', 'Devolución de cliente registrada.');
    }
}

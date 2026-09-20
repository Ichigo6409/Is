<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KardexController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $type = trim((string) $request->query('type', ''));
        $perPage = 25;

        $query = StockMovement::where('business_id', $this->businessId);

        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $query->where(function ($sub) use ($regex) {
                $sub->where('external_reference', 'regex', $regex)
                    ->orWhere('reason', 'regex', $regex);
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Se cargan los productos del negocio una sola vez para resolver
        // SKU/nombre por movimiento sin hacer una consulta por fila (N+1).
        $productsMap = Product::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($p) => (string) $p->_id);

        $data = collect($movements->items())->map(function ($m) use ($productsMap) {
            $product = $productsMap->get((string) $m->product_id);
            return [
                '_id' => (string) $m->_id,
                'type' => (string) $m->type,
                'product_sku' => $product ? (string) $product->sku : '',
                'product_name' => $product ? (string) $product->name : '',
                'quantity' => (int) $m->quantity,
                'reason' => (string) ($m->reason ?? ''),
                'external_reference' => (string) ($m->external_reference ?? ''),
                'actor_id' => (string) ($m->actor_id ?? 'SYSTEM'),
                'created_at' => $m->created_at instanceof \DateTimeInterface
                    ? $m->created_at->format('d/m/Y H:i')
                    : (string) ($m->created_at ?? ''),
            ];
        })->values()->all();

        return Inertia::render('Equipo4/Kardex', [
            'movements' => $data,
            'pagination' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
                'from' => $movements->firstItem() ?? 0,
                'to' => $movements->lastItem() ?? 0,
            ],
            'filters' => [
                'q' => $q,
                'type' => $type,
            ],
        ]);
    }
}

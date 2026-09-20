<?php

namespace App\Http\Controllers;

use App\Models\PricingReference;
use App\Models\Product;
use App\Services\CostService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MongoDB\BSON\ObjectId;

class CostController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    private function formatRawDate($value, string $format = 'Y-m-d H:i'): ?string
    {
        if ($value === null) return null;

        try {
            if ($value instanceof \DateTimeInterface) {
                return $value->format($format);
            }
            if (is_string($value)) {
                return Carbon::parse($value)->format($format);
            }
            if (is_array($value) && isset($value['$date'])) {
                return Carbon::parse($value['$date'])->format($format);
            }
            if (is_object($value) && method_exists($value, 'toDateTime')) {
                return Carbon::instance($value->toDateTime())->format($format);
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = 25;

        $productQuery = Product::where('business_id', $this->businessId);

        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $productQuery->where(function ($sub) use ($regex) {
                $sub->where('sku', 'regex', $regex)
                    ->orWhere('name', 'regex', $regex);
            });
        }

        $products = $productQuery->orderBy('name', 'asc')->paginate($perPage);

        $refsMap = [];
        $allRefs = PricingReference::where('business_id', $this->businessId)->get();
        foreach ($allRefs as $ref) {
            $refsMap[(string) $ref->product_id] = $ref;
        }

        $data = collect($products->items())->map(function ($p) use ($refsMap) {
            $ref = $refsMap[(string) $p->_id] ?? null;

            return [
                '_id' => (string) $p->_id,
                'sku' => (string) $p->sku,
                'name' => (string) $p->name,
                'category' => (string) $p->category,
                'last_cost' => $ref ? (float) $ref->last_cost : null,
                'average_cost' => $ref ? (float) $ref->average_cost : null,
                'suggested_margin' => $ref ? (float) $ref->suggested_margin : null,
                'suggested_price' => $ref && isset($ref->suggested_price) ? (float) $ref->suggested_price : null,
                'has_costs' => $ref !== null,
            ];
        })->values()->all();

        $productsList = Product::where('business_id', $this->businessId)
            ->where('active', true)
            ->orderBy('name', 'asc')
            ->get()
            ->map(fn($p) => ['_id' => (string) $p->_id, 'sku' => (string) $p->sku, 'name' => (string) $p->name])
            ->values()->all();

        return Inertia::render('Equipo4/Costos', [
            'products' => $data,
            'productsList' => $productsList,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem() ?? 0,
                'to' => $products->lastItem() ?? 0,
            ],
            'filters' => ['q' => $q],
        ]);
    }

    public function store(Request $request, CostService $costService): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'cost' => 'required|numeric|min:0|max:1000000',
            'source' => 'nullable|string|in:MANUAL,PURCHASE_ORDER,RECEIPT',
            'reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'suggested_price' => 'nullable|numeric|min:0|max:10000000',
        ]);

        try {
            $costService->register([
                'product_id' => $validated['product_id'],
                'cost' => (float) $validated['cost'],
                'source' => $validated['source'] ?? 'MANUAL',
                'reference' => $validated['reference'] ?? '',
                'notes' => $validated['notes'] ?? '',
                'suggested_price' => $validated['suggested_price'] ?? null,
                'actor_id' => 'USR-ADMIN-001',
            ]);
        } catch (\Throwable $e) {
            Log::error('Fallo al registrar costo.', ['error' => $e->getMessage()]);
            return redirect()->route('equipo4.costos.index')
                ->withErrors(['error' => 'No se pudo registrar: ' . $e->getMessage()]);
        }

        return redirect()->route('equipo4.costos.index')
            ->with('success', 'Costo registrado y referencia actualizada.');
    }

    /**
     * Historial de costos. Consulta directo a la coleccion 'cost_histories'
     * (evita problemas de nombre de coleccion del modelo).
     */
    public function history(string $productId)
    {
        try {
            $collection = DB::connection('mongodb')->getCollection('cost_histories');

            $cursor = $collection->find(
                [
                    'business_id' => $this->businessId,
                    'product_id' => $productId,
                ],
                [
                    'sort' => ['created_at' => -1],
                    'limit' => 30,
                ]
            );

            $data = [];
            foreach ($cursor as $doc) {
                $doc = (array) $doc;

                $data[] = [
                    '_id' => (string) ($doc['_id'] ?? ''),
                    'cost' => (float) ($doc['cost'] ?? 0),
                    'source' => (string) ($doc['source'] ?? ''),
                    'reference' => (string) ($doc['reference'] ?? ''),
                    'notes' => (string) ($doc['notes'] ?? ''),
                    'effective_at' => $this->formatRawDate($doc['effective_at'] ?? $doc['created_at'] ?? null),
                ];
            }

            return response()->json(['history' => $data]);
        } catch (\Throwable $e) {
            Log::error('Fallo al cargar historial de costos.', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['history' => [], 'error' => $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $perPage = 25;

        $query = Product::where('business_id', $this->businessId);

        // Busqueda server-side con regex escapado (case-insensitive)
        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $query->where(function ($sub) use ($regex) {
                $sub->where('sku', 'regex', $regex)
                    ->orWhere('name', 'regex', $regex);
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $productsData = collect($products->items())->map(function ($product) {
            return [
                '_id' => (string) $product->_id,
                'sku' => (string) $product->sku,
                'name' => (string) $product->name,
                'description' => (string) ($product->description ?? ''),
                'category' => (string) $product->category,
                'stock_min' => (int) $product->stock_min,
                'stock_max' => (int) $product->stock_max,
                'active' => (bool) $product->active,
                'created_at' => $product->created_at ? $product->created_at->toIso8601String() : null,
                'updated_at' => $product->updated_at ? $product->updated_at->toIso8601String() : null,
            ];
        })->values()->all();

        $categories = Category::where('business_id', $this->businessId)
            ->where('active', true)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($cat) {
                return [
                    'slug' => (string) $cat->slug,
                    'name' => (string) $cat->name,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Equipo4/Productos', [
            'products' => $productsData,
            'categories' => $categories,
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
                'from' => $products->firstItem() ?? 0,
                'to' => $products->lastItem() ?? 0,
            ],
            'filters' => [
                'q' => $q,
            ],
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['business_id'] = $this->businessId;

        Product::create($validated);

        return redirect()->back()->with('success', 'Producto creado exitosamente.');
    }

    public function update(UpdateProductRequest $request, string $id): RedirectResponse
    {
        $product = Product::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$product) {
            abort(404, 'Producto no encontrado.');
        }

        $product->update($request->validated());

        return redirect()->back()->with('success', 'Producto actualizado exitosamente.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $product = Product::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$product) {
            abort(404, 'Producto no encontrado.');
        }

        $product->delete();

        return redirect()->back()->with('success', 'Producto eliminado exitosamente.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(): Response
    {
        $products = Product::where('business_id', $this->businessId)
            ->orderBy('created_at', 'desc')
            ->take(100)
            ->get()
            ->map(function ($product) {
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
            });

        $categories = Category::where('business_id', $this->businessId)
            ->where('active', true)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($cat) {
                return [
                    'slug' => (string) $cat->slug,
                    'name' => (string) $cat->name,
                ];
            });

        return Inertia::render('Equipo4/Productos', [
            'products' => $products,
            'categories' => $categories,
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

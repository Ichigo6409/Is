<?php

namespace App\Http\Controllers;

use App\Models\StockAlert;
use App\Models\Product;
use App\Models\Location;
use App\Models\ReorderRule;
use App\Services\AlertGeneratorService;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;

class AlertController extends Controller
{
    protected string $businessId = 'BUS-CD-SOUV-001';

    public function index(Request $request): Response
    {
        // Auto-sync cada 5 segundos maximo
        $cacheKey = 'equipo4_alerts_last_generate';
        if (!Cache::has($cacheKey)) {
            try {
                app(AlertGeneratorService::class)->generate();
                Cache::put($cacheKey, now()->timestamp, 5);
            } catch (\Throwable $e) {
                // no-op
            }
        }

        $q = trim((string) $request->query('q', ''));
        $showHistory = $request->boolean('history', false);

        // Por defecto solo ACTIVAS. Con ?history=1 incluye resueltas/descartadas.
        $query = StockAlert::where('business_id', $this->businessId);

        if (!$showHistory) {
            $query->where('status', 'ACTIVE');
        }

        if ($q !== '') {
            $regex = '/' . preg_quote($q, '/') . '/i';
            $query->where('message', 'regex', $regex);
        }

        $alerts = $query->orderBy('created_at', 'desc')->limit(200)->get();

        $productsMap = Product::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($p) => (string) $p->_id);

        $locationsMap = Location::where('business_id', $this->businessId)
            ->get()
            ->keyBy(fn($l) => (string) $l->_id);

        $alertsData = $alerts->map(function ($a) use ($productsMap, $locationsMap) {
            $product = $productsMap->get((string) $a->product_id);
            $location = $locationsMap->get((string) $a->location_id);

            return [
                '_id' => (string) $a->_id,
                'product_sku' => $product ? (string) $product->sku : '—',
                'product_name' => $product ? (string) $product->name : '—',
                'location_name' => $location ? (string) $location->name : '—',
                'current_qty' => (int) $a->current_qty,
                'threshold' => (int) $a->threshold,
                'priority' => (string) $a->priority,
                'status' => (string) $a->status,
                'message' => (string) ($a->message ?? ''),
                'resolution_reason' => (string) ($a->resolution_reason ?? ''),
            ];
        })->values()->all();

        $rules = ReorderRule::where('business_id', $this->businessId)
            ->orderBy('created_at', 'desc')
            ->get();

        $rulesData = $rules->map(function ($r) use ($productsMap, $locationsMap) {
            $product = $productsMap->get((string) $r->product_id);
            $location = $locationsMap->get((string) $r->location_id);

            return [
                '_id' => (string) $r->_id,
                'product_id' => (string) $r->product_id,
                'product_sku' => $product ? (string) $product->sku : '—',
                'product_name' => $product ? (string) $product->name : '—',
                'location_id' => (string) $r->location_id,
                'location_name' => $location ? (string) $location->name : '—',
                'min_qty' => (int) $r->min_qty,
                'max_qty' => (int) $r->max_qty,
                'reorder_point' => (int) $r->reorder_point,
                'active' => (bool) $r->active,
            ];
        })->values()->all();

        $productsList = $productsMap->map(fn($p) => [
            '_id' => (string) $p->_id,
            'sku' => (string) $p->sku,
            'name' => (string) $p->name,
        ])->values()->all();

        $locationsList = $locationsMap->map(fn($l) => [
            '_id' => (string) $l->_id,
            'code' => (string) $l->code,
            'name' => (string) $l->name,
        ])->values()->all();

        return Inertia::render('Equipo4/Alertas', [
            'alerts' => $alertsData,
            'rules' => $rulesData,
            'productsList' => $productsList,
            'locationsList' => $locationsList,
            'showHistory' => $showHistory,
            'filters' => ['q' => $q, 'history' => $showHistory ? '1' : ''],
        ]);
    }

    public function generate(AlertGeneratorService $generator): RedirectResponse
    {
        try {
            $stats = $generator->generate();
            Cache::forget('equipo4_alerts_last_generate');

            $msg = sprintf(
                'Alertas recalculadas. Creadas: %d, Actualizadas: %d, Resueltas: %d',
                $stats['created'],
                $stats['updated'],
                $stats['resolved']
            );

            return redirect()->route('equipo4.alertas.index')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->route('equipo4.alertas.index')
                ->withErrors(['error' => 'Error al generar alertas: ' . $e->getMessage()]);
        }
    }

    public function discard(Request $request, string $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:500',
        ], [
            'reason.required' => 'Debes indicar el motivo del descarte.',
            'reason.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $alert = StockAlert::where('_id', $id)
            ->where('business_id', $this->businessId)
            ->first();

        if (!$alert) {
            return redirect()->route('equipo4.alertas.index')
                ->withErrors(['error' => 'Alerta no encontrada.']);
        }

        DB::connection('mongodb')->getCollection('stock_alerts')->updateOne(
            ['_id' => new ObjectId($id)],
            ['$set' => [
                'status' => 'DISMISSED',
                'resolution_reason' => $validated['reason'],
                'dismissed_by' => 'USR-ADMIN-001',
                'dismissed_at' => now()->toDateTime(),
                'updated_at' => now()->toDateTime(),
            ]]
        );

        return redirect()->route('equipo4.alertas.index')
            ->with('success', 'Alerta descartada.');
    }
}

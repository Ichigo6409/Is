<?php

namespace App\Api;

use App\Http\Controllers\Controller;
use App\Services\Team7AdapterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Team7ApiController extends Controller
{
    public function __construct(protected Team7AdapterService $adapter) {}

    public function availability(Request $request)
    {
        $v = $request->validate([
            'items'                     => 'required|array|min:1|max:100',
            'items.*.product_reference' => 'required|string',
            'items.*.variant_sku'       => 'nullable|string',
            'items.*.location_id'       => 'nullable|string',
            'items.*.quantity'          => 'required|integer|min:1|max:1000',
        ]);
        try {
            return response()->json($this->adapter->availability($v['items']), 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('E7 availability failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al verificar disponibilidad.'], 500);
        }
    }

    public function reserve(Request $request)
    {
        $v = $request->validate([
            'origin_reference'          => 'required|string|max:100',
            'origin_type'               => 'required|string|max:50',
            'items'                     => 'required|array|min:1|max:100',
            'items.*.product_reference' => 'required|string',
            'items.*.variant_sku'       => 'nullable|string',
            'items.*.location_id'       => 'nullable|string',
            'items.*.quantity'          => 'required|integer|min:1|max:1000',
            'idempotency_key'           => 'required|string|min:8|max:128',
            'expires_at'                => 'nullable|date|after:now',
        ]);
        try {
            return response()->json($this->adapter->reserve($v), 201);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'rejected', 'reason' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('E7 reserve failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al reservar.'], 500);
        }
    }

    public function confirm(Request $request, string $id)
    {
        $v = $request->validate([
            'delivered_at'    => 'nullable|date',
            'delivered_by'    => 'nullable|string|max:100',
            'idempotency_key' => 'required|string|min:8|max:128',
        ]);
        try {
            return response()->json($this->adapter->confirm($id, $v), 200);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('E7 confirm failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al confirmar.'], 500);
        }
    }

    public function release(Request $request, string $id)
    {
        $v = $request->validate([
            'reason'          => 'nullable|string|max:200',
            'idempotency_key' => 'required|string|min:8|max:128',
        ]);
        try {
            return response()->json($this->adapter->release($id, $v), 200);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('E7 release failed', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al liberar.'], 500);
        }
    }

    public function show(Request $request, string $id)
    {
        $r = $this->adapter->show($id);
        if ($r === null) return response()->json(['message' => 'Reserva no encontrada'], 404);
        return response()->json($r, 200);
    }
}

<?php

namespace App\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Team3IntegrationController extends Controller
{
    public function __construct(protected Team3ApiService $api) {}

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|string',
            'items.*.variant_id' => 'nullable|string',
            'items.*.location_id' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
        ]);

        try {
            return response()->json($this->api->availability($validated['items']), 200);
        } catch (\Throwable $e) {
            Log::error('Team3 availability failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al verificar disponibilidad.'], 500);
        }
    }

    public function reserve(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|string|max:100',
            'items' => 'required|array|min:1|max:100',
            'items.*.product_id' => 'required|string',
            'items.*.variant_id' => 'nullable|string',
            'items.*.location_id' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1|max:100000',
            'idempotency_key' => 'required|string|min:8|max:128',
            'source' => 'nullable|string|in:CHECKOUT,REWARD,CANJE,ORDER',
            'external_reference' => 'nullable|string|max:200',
            'expires_at' => 'nullable|date|after:now',
            'actor_id' => 'nullable|string|max:100',
        ]);

        try {
            return response()->json($this->api->reserve($validated), 201);
        } catch (\DomainException $e) {
            return response()->json(['status' => 'REJECTED', 'reason' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            Log::error('Team3 reserve failed.', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al reservar stock.'], 500);
        }
    }

    public function confirm(Request $request, string $id)
    {
        $validated = $request->validate([
            'order_id' => 'required|string|max:100',
            'idempotency_key' => 'required|string|min:8|max:128',
            'actor_id' => 'nullable|string|max:100',
        ]);

        try {
            return response()->json($this->api->confirm($id, $validated), 200);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Team3 confirm failed.', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al confirmar reserva.'], 500);
        }
    }

    public function release(Request $request, string $id)
    {
        $validated = $request->validate([
            'order_id' => 'required|string|max:100',
            'reason' => 'nullable|string|max:200',
            'idempotency_key' => 'required|string|min:8|max:128',
        ]);

        try {
            return response()->json($this->api->release($id, $validated), 200);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Log::error('Team3 release failed.', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al liberar reserva.'], 500);
        }
    }
}

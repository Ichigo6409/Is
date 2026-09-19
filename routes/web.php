<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Team4Controller;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\LocationController;

Route::prefix('equipo4')->group(function () {
    Route::get('/', [Team4Controller::class, 'dashboard']);

    // Productos
    Route::get('/productos', [ProductController::class, 'index']);
    Route::post('/productos', [ProductController::class, 'store']);
    Route::put('/productos/{id}', [ProductController::class, 'update']);
    Route::delete('/productos/{id}', [ProductController::class, 'destroy']);

    // Proveedores
    Route::get('/proveedores', [SupplierController::class, 'index']);
    Route::post('/proveedores', [SupplierController::class, 'store']);
    Route::put('/proveedores/{id}', [SupplierController::class, 'update']);
    Route::delete('/proveedores/{id}', [SupplierController::class, 'destroy']);

    // Almacenes
    Route::get('/almacenes', [WarehouseController::class, 'index']);
    Route::post('/almacenes', [WarehouseController::class, 'store']);
    Route::put('/almacenes/{id}', [WarehouseController::class, 'update']);
    Route::delete('/almacenes/{id}', [WarehouseController::class, 'destroy']);

    // Ubicaciones
    Route::post('/ubicaciones', [LocationController::class, 'store']);
    Route::put('/ubicaciones/{id}', [LocationController::class, 'update']);
    Route::delete('/ubicaciones/{id}', [LocationController::class, 'destroy']);

    // Módulos pendientes (vistas estáticas por ahora)
    $pages = [
        'kardex' => 'Kardex',
        'compras' => 'Compras',
        'recepciones' => 'Recepciones',
        'devoluciones' => 'Devoluciones',
        'costos' => 'Costos',
        'reservas' => 'Reservas',
        'alertas' => 'Alertas',
        'conteos' => 'Conteos',
        'souvenirs' => 'Souvenirs',
        'inventario' => 'Inventario',
    ];
    foreach ($pages as $uri => $page) {
        Route::get('/' . $uri, fn() => app(Team4Controller::class)->page($page));
    }
});

Route::prefix('api/equipo4')->group(function () {
    Route::get('/products', [Team4Controller::class, 'products'])->middleware('throttle:60,1');
    Route::get('/warehouses', [Team4Controller::class, 'warehouses'])->middleware('throttle:60,1');
    Route::get('/locations', [Team4Controller::class, 'locations'])->middleware('throttle:60,1');
    Route::get('/inventory', [Team4Controller::class, 'inventory'])->middleware('throttle:60,1');
    Route::get('/movements', [Team4Controller::class, 'movements'])->middleware('throttle:60,1');
    Route::get('/suppliers', [Team4Controller::class, 'suppliers'])->middleware('throttle:60,1');
    Route::get('/purchase-orders', [Team4Controller::class, 'purchaseOrders'])->middleware('throttle:60,1');
    Route::get('/returns', fn() => response()->json([
        'supplier' => \App\Models\SupplierReturn::orderBy('created_at', 'desc')->limit(100)->get(),
        'customer' => \App\Models\CustomerReturn::orderBy('created_at', 'desc')->limit(100)->get(),
    ]))->middleware('throttle:60,1');

    Route::middleware(['team4.signature', 'throttle:120,1'])->group(function () {
        Route::get('/integration/availability', [IntegrationController::class, 'availability']);
        Route::post('/integration/reservations', [IntegrationController::class, 'reserve']);
        Route::post('/integration/supplier-returns', [IntegrationController::class, 'supplierReturn']);
        Route::post('/integration/customer-returns', [IntegrationController::class, 'customerReturn']);
    });
});

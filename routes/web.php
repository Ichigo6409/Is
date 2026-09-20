<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Team4Controller;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;

Route::prefix('equipo4')->group(function () {
    Route::get('/', [Team4Controller::class, 'dashboard'])->name('equipo4.dashboard');

    Route::get('/productos', [ProductController::class, 'index'])->name('equipo4.productos.index');
    Route::post('/productos', [ProductController::class, 'store'])->name('equipo4.productos.store');
    Route::put('/productos/{id}', [ProductController::class, 'update'])->name('equipo4.productos.update');
    Route::delete('/productos/{id}', [ProductController::class, 'destroy'])->name('equipo4.productos.destroy');

    Route::get('/proveedores', [SupplierController::class, 'index'])->name('equipo4.proveedores.index');
    Route::post('/proveedores', [SupplierController::class, 'store'])->name('equipo4.proveedores.store');
    Route::put('/proveedores/{id}', [SupplierController::class, 'update'])->name('equipo4.proveedores.update');
    Route::delete('/proveedores/{id}', [SupplierController::class, 'destroy'])->name('equipo4.proveedores.destroy');

    Route::get('/almacenes', [WarehouseController::class, 'index'])->name('equipo4.almacenes.index');
    Route::post('/almacenes', [WarehouseController::class, 'store'])->name('equipo4.almacenes.store');
    Route::put('/almacenes/{id}', [WarehouseController::class, 'update'])->name('equipo4.almacenes.update');
    Route::delete('/almacenes/{id}', [WarehouseController::class, 'destroy'])->name('equipo4.almacenes.destroy');

    Route::post('/ubicaciones', [LocationController::class, 'store'])->name('equipo4.ubicaciones.store');
    Route::put('/ubicaciones/{id}', [LocationController::class, 'update'])->name('equipo4.ubicaciones.update');
    Route::delete('/ubicaciones/{id}', [LocationController::class, 'destroy'])->name('equipo4.ubicaciones.destroy');

    Route::get('/compras', [PurchaseOrderController::class, 'index'])->name('equipo4.compras.index');
    Route::post('/compras', [PurchaseOrderController::class, 'store'])->name('equipo4.compras.store');
    Route::get('/compras/{id}/details', [PurchaseOrderController::class, 'show'])->name('equipo4.compras.show');
    Route::put('/compras/{id}', [PurchaseOrderController::class, 'update'])->name('equipo4.compras.update');
    Route::patch('/compras/{id}/status', [PurchaseOrderController::class, 'changeStatus'])->name('equipo4.compras.status');
    Route::delete('/compras/{id}', [PurchaseOrderController::class, 'destroy'])->name('equipo4.compras.destroy');

    Route::get('/recepciones', [GoodsReceiptController::class, 'index'])->name('equipo4.recepciones.index');
    Route::post('/recepciones', [GoodsReceiptController::class, 'store'])->name('equipo4.recepciones.store');
    Route::delete('/recepciones/{id}', [GoodsReceiptController::class, 'destroy'])->name('equipo4.recepciones.destroy');

    $pages = [
        'kardex' => 'Kardex',
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

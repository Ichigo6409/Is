<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Team4Controller;
use App\Http\Controllers\IntegrationController;

Route::prefix('equipo4')->group(function () {
    Route::get('/', [Team4Controller::class,'dashboard']);
    $pages = [
        'inventario'=>'Inventario','productos'=>'Productos','almacenes'=>'Almacenes',
        'kardex'=>'Kardex','proveedores'=>'Proveedores','compras'=>'Compras',
        'recepciones'=>'Recepciones','devoluciones'=>'Devoluciones','costos'=>'Costos','reservas'=>'Reservas',
        'alertas'=>'Alertas','conteos'=>'Conteos','souvenirs'=>'Souvenirs'
    ];
    foreach ($pages as $uri=>$page) {
        Route::get('/'.$uri, fn()=>app(Team4Controller::class)->page($page));
    }
});

Route::prefix('api/equipo4')->group(function () {
    Route::get('/products',[Team4Controller::class,'products'])->middleware('throttle:60,1');
    Route::get('/warehouses',[Team4Controller::class,'warehouses'])->middleware('throttle:60,1');
    Route::get('/locations',[Team4Controller::class,'locations'])->middleware('throttle:60,1');
    Route::get('/inventory',[Team4Controller::class,'inventory'])->middleware('throttle:60,1');
    Route::get('/movements',[Team4Controller::class,'movements'])->middleware('throttle:60,1');
    Route::get('/suppliers',[Team4Controller::class,'suppliers'])->middleware('throttle:60,1');
    Route::get('/purchase-orders',[Team4Controller::class,'purchaseOrders'])->middleware('throttle:60,1');
    Route::get('/returns',fn()=>response()->json([
        'supplier'=>\App\Models\SupplierReturn::orderBy('created_at','desc')->limit(100)->get(),
        'customer'=>\App\Models\CustomerReturn::orderBy('created_at','desc')->limit(100)->get(),
    ]))->middleware('throttle:60,1');

    Route::middleware(['team4.signature','throttle:120,1'])->group(function () {
        Route::get('/integration/availability',[IntegrationController::class,'availability']);
        Route::post('/integration/reservations',[IntegrationController::class,'reserve']);
        Route::post('/integration/supplier-returns',[IntegrationController::class,'supplierReturn']);
        Route::post('/integration/customer-returns',[IntegrationController::class,'customerReturn']);
    });
});

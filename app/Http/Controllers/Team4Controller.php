<?php
namespace App\Http\Controllers;

use App\Models\{Product,Warehouse,Location,Inventory,StockMovement,Supplier,PurchaseOrder,StockReservation};
use Illuminate\Http\Request;
use Inertia\Inertia;

class Team4Controller extends Controller
{
    public function dashboard() { return Inertia::render('Equipo4/Dashboard', ['stats'=>[
        'products'=>Product::count(),'warehouses'=>Warehouse::count(),
        'inventory'=>Inventory::sum('on_hand'),'reservations'=>StockReservation::where('status','RESERVED')->count()
    ]]); }

    public function page(string $module) {
        $allowed = [
            'Inventario','Productos','Almacenes','Kardex','Proveedores','Compras',
            'Recepciones','Costos','Reservas','Devoluciones','Alertas','Conteos','Souvenirs'
        ];
        abort_unless(in_array($module,$allowed,true),404);
        return Inertia::render('Equipo4/'.$module);
    }

    public function products(Request $r) {
        $q = trim((string)$r->query('q',''));
        $items = Product::query()->when($q, fn($x)=>$x->where('sku','like','%'.$q.'%')
            ->orWhere('name','like','%'.$q.'%'))->limit(100)->get();
        return response()->json($items);
    }

    public function warehouses() { return response()->json(Warehouse::limit(100)->get()); }
    public function locations() { return response()->json(Location::limit(200)->get()); }
    public function inventory() { return response()->json(Inventory::limit(500)->get()); }
    public function movements() { return response()->json(StockMovement::orderBy('created_at','desc')->limit(200)->get()); }
    public function suppliers() { return response()->json(Supplier::limit(100)->get()); }
    public function purchaseOrders() { return response()->json(PurchaseOrder::orderBy('created_at','desc')->limit(100)->get()); }
}

const d = db.getSiblingDB("campus_digital");

const now = new Date();

d.businesses.updateOne(
  { code: "CD-SOUV" },
  { $setOnInsert: {
      code: "CD-SOUV",
      name: "Tienda Campus Digital",
      type: "MIXED",
      active: true,
      created_at: now,
      updated_at: now
  }},
  { upsert: true }
);

const business = d.businesses.findOne({ code: "CD-SOUV" });

const products = [
  { sku:"PR-001", name:"Playera Campus Digital", category:"Ropa", active:true, stock_min:10, stock_max:100 },
  { sku:"PR-002", name:"Taza Campus Digital", category:"Souvenirs", active:true, stock_min:8, stock_max:60 },
  { sku:"PR-003", name:"Libreta institucional", category:"Papelería", active:true, stock_min:15, stock_max:120 }
];

products.forEach(p => d.products.updateOne(
  { sku:p.sku },
  { $set: {...p, business_id:business._id, updated_at:now}, $setOnInsert:{created_at:now} },
  { upsert:true }
));

d.warehouses.updateOne(
  { business_id:business._id, code:"ALM-001" },
  { $setOnInsert:{
      business_id:business._id, code:"ALM-001", name:"Almacén principal",
      type:"MAIN", active:true, created_at:now, updated_at:now
  }},
  { upsert:true }
);

d.warehouses.updateOne(
  { business_id:business._id, code:"ALM-002" },
  { $setOnInsert:{
      business_id:business._id, code:"ALM-002", name:"Bodega souvenirs",
      type:"STORAGE", active:true, created_at:now, updated_at:now
  }},
  { upsert:true }
);

const warehouses = d.warehouses.find({business_id:business._id}).toArray();
warehouses.forEach((w, i) => {
  d.locations.updateOne(
    { warehouse_id:w._id, code:"LOC-"+String(i+1).padStart(3,"0") },
    { $setOnInsert:{
        warehouse_id:w._id, business_id:business._id,
        code:"LOC-"+String(i+1).padStart(3,"0"),
        name:i===0 ? "Zona general" : "Estantería souvenirs",
        type:i===0 ? "STORAGE" : "DISPLAY",
        capacity:200, active:true, created_at:now, updated_at:now
    }},
    { upsert:true }
  );
});

const locations = d.locations.find({business_id:business._id}).toArray();
const ps = d.products.find({business_id:business._id}).toArray();

ps.forEach((p, i) => {
  const loc = locations[i % locations.length];
  const qty = [35, 22, 50][i];
  d.inventories.updateOne(
    {business_id:business._id, product_id:p._id, variant_id:null, location_id:loc._id},
    {$set:{
      business_id:business._id, product_id:p._id, variant_id:null,
      location_id:loc._id, on_hand:qty, reserved:0, available:qty,
      status:"AVAILABLE", updated_at:now
    }, $setOnInsert:{created_at:now}},
    {upsert:true}
  );
});


const firstProduct = d.products.findOne({business_id:business._id, sku:"PR-001"});
const firstLocation = d.locations.findOne({business_id:business._id});

d.supplier_returns.updateOne(
  {business_id:business._id, folio:"DEV-PROV-0001"},
  {$setOnInsert:{
    business_id:business._id, folio:"DEV-PROV-0001", supplier_id:null,
    status:"CONFIRMED", reason:"Producto defectuoso",
    source_type:"PURCHASE_ORDER", source_reference:"OC-00025",
    created_by:"USR-ADMIN-001", created_at:now, updated_at:now
  }},
  {upsert:true}
);

d.customer_returns.updateOne(
  {business_id:business._id, folio:"DEV-CLI-0001"},
  {$setOnInsert:{
    business_id:business._id, folio:"DEV-CLI-0001",
    customer_id:"ALU-0001", customer_type:"ALUMNO",
    status:"RECEIVED", reason:"Producto no deseado",
    sale_reference:"VENTA-00041",
    resolution:"RESTOCK", created_by:"USR-ADMIN-001",
    created_at:now, updated_at:now
  }},
  {upsert:true}
);

const cr=d.customer_returns.findOne({business_id:business._id, folio:"DEV-CLI-0001"});
if(firstProduct && firstLocation) {
  d.customer_return_items.updateOne(
    {customer_return_id:cr._id,line:1},
    {$setOnInsert:{
      customer_return_id:cr._id,line:1,product_id:firstProduct._id,
      variant_id:null,location_id:firstLocation._id,quantity:1,
      condition:"GOOD",resolution:"RESTOCK"
    }},
    {upsert:true}
  );
}
print("Seed de Equipo 4 completado, incluyendo devoluciones a proveedor y de cliente.");


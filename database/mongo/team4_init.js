// Campus Digital - Equipo 4
// MongoDB 8.x - inicialización completa del dominio de Inventarios,
// Proveedores, Compras y Abastecimiento.
//
// Ejecutar:
// mongosh "mongodb://127.0.0.1:27017" database/mongo/team4_init.js
//
// Para Atlas, sustituir la URI por la del cluster.

const dbName = "campus_digital";
const d = db.getSiblingDB(dbName);

const collections = [
  "businesses",
  "products",
  "product_variants",
  "warehouses",
  "locations",
  "inventories",
  "stock_movements",
  "suppliers",
  "supplier_contacts",
  "supplier_products",
  "purchase_orders",
  "purchase_order_items",
  "goods_receipts",
  "goods_receipt_items",
  "supplier_returns",
  "supplier_return_items",
  "customer_returns",
  "customer_return_items",
  "cost_history",
  "pricing_references",
  "stock_reservations",
  "reorder_rules",
  "stock_alerts",
  "stock_counts",
  "stock_count_items",
  "adjustments",
  "collections",
  "merchandising_metadata",
  "integration_idempotency",
  "integration_logs",
  "audit_logs"
];

collections.forEach(c => {
  if (!d.getCollectionNames().includes(c)) d.createCollection(c);
});

// Productos y variantes
d.products.createIndex({ sku: 1 }, { unique: true });
d.products.createIndex({ business_id: 1, active: 1 });
d.products.createIndex({ name: 1 });
d.product_variants.createIndex({ product_id: 1, sku: 1 }, { unique: true });
d.product_variants.createIndex({ product_id: 1, active: 1 });

// Almacenes / ubicaciones
d.businesses.createIndex({ code: 1 }, { unique: true });
d.warehouses.createIndex({ business_id: 1, code: 1 }, { unique: true });
d.locations.createIndex({ warehouse_id: 1, code: 1 }, { unique: true });
d.locations.createIndex({ warehouse_id: 1, type: 1, active: 1 });

// Inventario
d.inventories.createIndex(
  { business_id: 1, product_id: 1, variant_id: 1, location_id: 1 },
  { unique: true }
);
d.inventories.createIndex({ business_id: 1, available: 1 });
d.stock_movements.createIndex({ business_id: 1, product_id: 1, created_at: -1 });
d.stock_movements.createIndex({ location_id: 1, created_at: -1 });
d.stock_movements.createIndex({ type: 1, created_at: -1 });
d.stock_movements.createIndex({ external_reference: 1 });

// Proveedores / compras
d.suppliers.createIndex({ business_id: 1, code: 1 }, { unique: true });
d.suppliers.createIndex({ business_id: 1, status: 1 });
d.supplier_contacts.createIndex({ supplier_id: 1 });
d.supplier_products.createIndex({ supplier_id: 1, product_id: 1 }, { unique: true });
d.purchase_orders.createIndex({ business_id: 1, folio: 1 }, { unique: true });
d.purchase_orders.createIndex({ supplier_id: 1, status: 1 });
d.purchase_order_items.createIndex({ purchase_order_id: 1, line: 1 }, { unique: true });
d.goods_receipts.createIndex({ purchase_order_id: 1, folio: 1 }, { unique: true });
d.supplier_returns.createIndex({ business_id: 1, folio: 1 }, { unique: true });
d.customer_returns.createIndex({ business_id: 1, folio: 1 }, { unique: true });
d.customer_returns.createIndex({ customer_id: 1, created_at: -1 });
d.customer_return_items.createIndex({ customer_return_id: 1, line: 1 }, { unique: true });

// Costos, precios y reservas
d.cost_history.createIndex({ product_id: 1, effective_at: -1 });
d.pricing_references.createIndex({ business_id: 1, product_id: 1 }, { unique: true });
d.stock_reservations.createIndex({ business_id: 1, status: 1, expires_at: 1 });
d.stock_reservations.createIndex({ external_reference: 1 });
d.reorder_rules.createIndex({ business_id: 1, product_id: 1, location_id: 1 }, { unique: true });
d.stock_alerts.createIndex({ business_id: 1, status: 1, created_at: -1 });

// Conteos y souvenirs
d.stock_counts.createIndex({ business_id: 1, status: 1, created_at: -1 });
d.stock_count_items.createIndex({ stock_count_id: 1, product_id: 1, location_id: 1 }, { unique: true });
d.adjustments.createIndex({ business_id: 1, created_at: -1 });
d.collections.createIndex({ business_id: 1, code: 1 }, { unique: true });
d.merchandising_metadata.createIndex({ product_id: 1 }, { unique: true });

// Seguridad / integraciones
d.integration_idempotency.createIndex({ service: 1, idempotency_key: 1 }, { unique: true });
d.integration_idempotency.createIndex({ expires_at: 1 }, { expireAfterSeconds: 0 });
d.integration_logs.createIndex({ correlation_id: 1 });
d.integration_logs.createIndex({ service: 1, created_at: -1 });
d.audit_logs.createIndex({ entity: 1, entity_id: 1, created_at: -1 });
d.audit_logs.createIndex({ actor_id: 1, created_at: -1 });

print(`Base ${dbName} inicializada para Equipo 4.`);
printjson(d.getCollectionNames().filter(x => collections.includes(x)));

// Validaciones básicas de documentos críticos.
// No sustituyen la validación Laravel; agregan defensa en profundidad.
function setValidator(name, validator) {
  d.runCommand({
    collMod:name,
    validator:validator,
    validationLevel:"strict",
    validationAction:"error"
  });
}
setValidator("customer_returns", {$jsonSchema:{
  bsonType:"object",
  required:["business_id","folio","status","reason","resolution","created_at"],
  properties:{
    business_id:{bsonType:"objectId"},
    folio:{bsonType:"string",maxLength:100},
    status:{enum:["RECEIVED","UNDER_REVIEW","APPROVED","REJECTED","CLOSED"]},
    reason:{bsonType:"string",maxLength:250},
    resolution:{enum:["RESTOCK","QUARANTINE","REPLACE","REFUND"]},
    customer_type:{enum:["ALUMNO","OTHER"]},
    created_at:{bsonType:"date"}
  }
}});
setValidator("supplier_returns", {$jsonSchema:{
  bsonType:"object",
  required:["business_id","folio","status","reason","created_at"],
  properties:{
    business_id:{bsonType:"objectId"},
    folio:{bsonType:"string",maxLength:100},
    status:{enum:["RECEIVED","UNDER_REVIEW","APPROVED","REJECTED","CONFIRMED","CLOSED"]},
    reason:{bsonType:"string",maxLength:250},
    created_at:{bsonType:"date"}
  }
}});
print("Validadores MongoDB aplicados.");

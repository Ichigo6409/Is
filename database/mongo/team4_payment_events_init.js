// Índices para la colección payment_events
const d = db.getSiblingDB("campus_digital");

d.payment_events.createIndex({ payment_intent_id: 1, status: 1 });
d.payment_events.createIndex({ order_id: 1 });
d.payment_events.createIndex({ received_at: -1 });

print("Índices de payment_events creados.");
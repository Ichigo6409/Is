\# Solicitud de Requerimiento - Eq.4 -> Eq.2



\## Info

\- Solicitante: Equipo 4 (Inventarios)

\- Destino: Equipo 2 (Wallet)

\- Prioridad: Alta

\- Fecha: 2026-09-29



\## Requerimiento

Publicar webhook firmado con el estado de cada payment\_intent, para

que Eq.4 pueda confirmar el descuento de inventario solo cuando el pago

haya sido realmente cobrado.



\## Endpoint destino

POST http://<host>:8004/api/equipo4/webhooks/payment-status



\## Headers

X-Team-Timestamp: unix segundos

X-Team-Signature: hmac-sha256 hex de "timestamp|POST|path|sha256(body)"

Content-Type: application/json

Accept: application/json



\## Body

{

&#x20; "payment\_intent\_id": "PI-01JC...",

&#x20; "order\_id": "ORD-2026-0001",

&#x20; "business\_id": "BUS-CD-SOUV-001",

&#x20; "status": "PAID",

&#x20; "amount": 150.00,

&#x20; "currency": "MXN",

&#x20; "components": \[{"type": "WALLET", "amount": 100.00}],

&#x20; "items": \[{"product\_id": "...", "variant\_id": null, "location\_id": "...", "quantity": 1}],

&#x20; "occurred\_at": "2026-09-29T16:00:00Z"

}



\## Estados

\- PENDING: nada (reserva sigue viva)

\- PAID: autoriza confirm (baja stock)

\- FAILED: habilita release

\- REFUNDED: auto-genera RETURN (si vienen items)

\- PARTIALLY\_REFUNDED: idem parcial



\## Respuesta esperada

202 {"received": true, "payment\_event\_id": "PE-..."}



\## Reglas

\- Idempotente por (payment\_intent\_id, status, amount). Retry devuelve {"idempotent": true}

\- Reintentos con backoff si 5xx: 5s, 30s, 5min, 30min, 2h (max 5)

\- Firma HMAC obligatoria en cada request

\- Para REFUNDED, incluir items con product\_id/variant\_id/location\_id/quantity



\## Secret

secreto\_equipo4\_eq3\_2026



\## Cierre

Bloqueante para Sprint 3. Endpoint desplegado y probado en Eq.4.


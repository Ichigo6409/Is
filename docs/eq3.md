```markdown

\# Integración con Equipo 3 — Marketplace



\*\*Rol de Eq.4\*\*: Servidor (exponemos API a Eq.3)

\*\*Controller\*\*: `App\\Api\\Team3IntegrationController`

\*\*Base path\*\*: `/api/equipo4/integration`



\## Contexto



Eq.3 vende productos de Eq.4. Antes de cerrar una venta, Eq.3 nos consulta disponibilidad, reserva stock, confirma el descuento cuando el pago pasa, y libera si se cancela.



Además, Eq.3 nos notifica devoluciones, transferencias entre almacenes y ajustes de inventario.



\## Endpoints expuestos (8)



Todos requieren firma HMAC-SHA256 con `TEAM\_SERVICE\_SHARED\_SECRET` (ver README).



\### 1. POST /api/equipo4/integration/availability



Request:

```json

{

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_id": "6abffeaf31a8cb527504f753",

&#x20;     "variant\_id": null,

&#x20;     "location\_id": "6abffeaf31a8cb527504f754",

&#x20;     "quantity": 2

&#x20;   }

&#x20; ]

}

```



Response (200):

```json

{

&#x20; "available": true,

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_id": "6abffeaf31a8cb527504f753",

&#x20;     "variant\_id": null,

&#x20;     "location\_id": "6abffeaf31a8cb527504f754",

&#x20;     "requested": 2,

&#x20;     "available": 125,

&#x20;     "has\_enough": true

&#x20;   }

&#x20; ]

}

```



Logica: has\_enough = true solo si hay stock en ESA ubicacion especifica.



\### 2. POST /api/equipo4/integration/reservations



Request:

```json

{

&#x20; "order\_id": "ORD-2026-ABC",

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_id": "6abffeaf31a8cb527504f753",

&#x20;     "variant\_id": null,

&#x20;     "location\_id": "6abffeaf31a8cb527504f754",

&#x20;     "quantity": 2

&#x20;   }

&#x20; ],

&#x20; "idempotency\_key": "reserve-ORD-2026-ABC-v1",

&#x20; "source": "CHECKOUT",

&#x20; "expires\_at": "2026-10-02T21:00:00-06:00"

}

```



Response (201):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "RESERVED",

&#x20; "items": \[],

&#x20; "expires\_at": "2026-10-02T21:00:00-06:00"

}

```



Response (409) sin stock:

```json

{

&#x20; "status": "REJECTED",

&#x20; "reason": "Existencia insuficiente para product\_id=..."

}

```



Idempotencia: misma idempotency\_key -> mismo reservation\_id (no duplica stock).



Estado inicial: Eq.4 crea la reserva directamente en RESERVED, NO usamos PENDING. Nuestra reserva es atomica (se descuenta available y se incrementa reserved en la misma operacion).



\### 3. POST /api/equipo4/integration/reservations/{id}/confirm



Request:

```json

{

&#x20; "order\_id": "ORD-2026-ABC",

&#x20; "payment\_intent\_id": "PI-2026-ABCD1234",

&#x20; "idempotency\_key": "confirm-ORD-2026-ABC-v1"

}

```



Response (200):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "CONFIRMED",

&#x20; "movements": \[

&#x20;   {

&#x20;     "product\_id": "6abffeaf31a8cb527504f753",

&#x20;     "quantity": -2,

&#x20;     "type": "SALE"

&#x20;   }

&#x20; ]

}

```



Verificacion critica: Eq.4 valida que existe un evento PAID con ese payment\_intent\_id en payment\_events (lleno via webhook de Eq.2). Si no existe:



Response (409):

```json

{

&#x20; "status": "REJECTED",

&#x20; "reason": "No hay registro de pago con payment\_intent\_id=PI-2026-ABCD1234"

}

```



Efecto en inventario: reduce on\_hand y reserved, registra movimiento en stock\_movements.



\### 4. POST /api/equipo4/integration/reservations/{id}/release



Request:

```json

{

&#x20; "order\_id": "ORD-2026-ABC",

&#x20; "reason": "CUSTOMER\_CANCELLED",

&#x20; "idempotency\_key": "release-ORD-2026-ABC-v1"

}

```



Response (200):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "RELEASED",

&#x20; "released\_at": "2026-10-02T20:00:00-06:00"

}

```



Idempotente: misma idempotency\_key -> mismo resultado.



\### 5. GET /api/equipo4/integration/reservations/{id}



Response (200):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "RESERVED | CONFIRMED | RELEASED",

&#x20; "items": \[],

&#x20; "expires\_at": "...",

&#x20; "payment": {

&#x20;   "payment\_intent\_id": "PI-...",

&#x20;   "status": "PAID",

&#x20;   "confirmed\_at": "..."

&#x20; }

}

```



\### 6. POST /api/equipo4/integration/returns



Request:

```json

{

&#x20; "order\_id": "ORD-2026-ABC",

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_id": "6abffeaf31a8cb527504f753",

&#x20;     "variant\_id": null,

&#x20;     "location\_id": "6abffeaf31a8cb527504f754",

&#x20;     "quantity": 1,

&#x20;     "reason": "DEFECT"

&#x20;   }

&#x20; ],

&#x20; "idempotency\_key": "return-ORD-2026-ABC-1"

}

```



Response (200):

```json

{

&#x20; "return\_id": "01JYYYY...",

&#x20; "movements": \[]

}

```



\### 7. POST /api/equipo4/integration/transfers



Request:

```json

{

&#x20; "from\_location\_id": "...",

&#x20; "to\_location\_id": "...",

&#x20; "items": \[

&#x20;   { "product\_id": "...", "quantity": 5 }

&#x20; ],

&#x20; "idempotency\_key": "transfer-XXX"

}

```



\### 8. POST /api/equipo4/integration/adjustments



Request:

```json

{

&#x20; "location\_id": "...",

&#x20; "items": \[

&#x20;   { "product\_id": "...", "quantity": -3, "reason": "SHRINKAGE" }

&#x20; ],

&#x20; "idempotency\_key": "adjust-XXX"

}

```



\## Errores comunes



| Codigo | Cuando |

|---|---|

| 401 | Firma HMAC invalida |

| 403 | business\_id distinto a BUS-CD-SOUV-001 |

| 404 | Reserva no encontrada |

| 409 | Estado invalido (ya confirmada, ya liberada, sin stock, sin pago) |

| 422 | Payload invalido |



\## Idempotencia



Todos los POST aceptan idempotency\_key. Eq.4 guarda el resultado en integration\_idempotency con indice unico en (service, idempotency\_key). Misma key -> mismo resultado, sin efectos duplicados.



\## Pendiente con Eq.3



1\. Confirmar que incluiran payment\_intent\_id en el body del /confirm — y de donde lo obtienen (¿se los pasa Eq.2?)

2\. Documentar que Eq.4 no usa PENDING — la reserva se crea directamente en RESERVED

```


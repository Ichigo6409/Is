```markdown

\# Integración con Equipo 7 — Recompensas



\*\*Rol de Eq.4\*\*: Servidor (exponemos API a Eq.7)

\*\*Controller\*\*: `App\\Api\\Team7ApiController`

\*\*Adapter\*\*: `App\\Services\\Team7AdapterService`

\*\*Base path\*\*: `/api/v1/inventory`



\## Contexto



Eq.7 canjea recompensas fisicas. Necesita reservar stock cuando un usuario aplica una recompensa, confirmar el consumo cuando se entrega, y liberar cuando expira.



Eq.4 expone una API adaptada al vocabulario de Eq.7 (SKUs en lugar de IDs de Mongo, origin\_reference en lugar de order\_id, etc.) que internamente reusa el mismo motor de reservas de Eq.3.



\## Endpoints expuestos (5)



Todos requieren firma HMAC-SHA256 con `TEAM\_SERVICE\_SHARED\_SECRET`.



\### 1. POST /api/v1/inventory/availability



Request:

```json

{

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_reference": "SOUV-TAZA-001",

&#x20;     "variant\_sku": null,

&#x20;     "location\_id": null,

&#x20;     "quantity": 2

&#x20;   }

&#x20; ]

}

```



Notas:

\- product\_reference acepta SKU (SOUV-TAZA-001) o ObjectId de Mongo (24 hex chars)

\- variant\_sku acepta SKU de variante o su ObjectId

\- location\_id es opcional — si no viene, Eq.4 elige una ubicacion con stock



Response (200):

```json

{

&#x20; "available": true,

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_reference": "SOUV-TAZA-001",

&#x20;     "variant\_sku": null,

&#x20;     "requested": 2,

&#x20;     "available": 48,

&#x20;     "has\_enough": true

&#x20;   }

&#x20; ]

}

```



\### 2. POST /api/v1/inventory/reservations



Request:

```json

{

&#x20; "origin\_reference": "RWD-2026-ABC123",

&#x20; "origin\_type": "REWARD",

&#x20; "items": \[

&#x20;   {

&#x20;     "product\_reference": "SOUV-TAZA-001",

&#x20;     "variant\_sku": null,

&#x20;     "location\_id": null,

&#x20;     "quantity": 1

&#x20;   }

&#x20; ],

&#x20; "idempotency\_key": "e7-reserve-RWD-2026-ABC123",

&#x20; "expires\_at": "2026-10-02T21:00:00-06:00"

}

```



Campos especificos de Eq.7:



| Campo | Valores | Mapeo interno |

|---|---|---|

| origin\_type | REWARD / CANJE / reward\_redemption | -> source en team3\_api\_reservations |

| origin\_reference | Libre | -> external\_reference |

| product\_reference | SKU o ObjectId | -> product\_id |

| variant\_sku | SKU o ObjectId o null | -> variant\_id |



Response (201):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "reserved",

&#x20; "items": \[],

&#x20; "expires\_at": "2026-10-02T21:00:00-06:00"

}

```



Mapeo de estados internos a publicos:



| Interno | Publico (Eq.7) |

|---|---|

| RESERVED | reserved |

| CONFIRMED | consumed |

| RELEASED (reason=EXPIRED) | expired |

| RELEASED (otro reason) | released |

| REJECTED | rejected |



\### 3. POST /api/v1/inventory/reservations/{id}/confirm



DIFERENCIA CLAVE con Eq.3: NO requiere payment\_intent\_id. Eq.7 es un canje, no un pago.



Request:

```json

{

&#x20; "delivered\_by": "STAFF-001",

&#x20; "delivered\_at": "2026-10-02T20:15:00-06:00",

&#x20; "idempotency\_key": "e7-confirm-01JXXXX"

}

```



Response (200):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "consumed",

&#x20; "stock\_movement\_id": "6712abc...",

&#x20; "confirmed\_at": "2026-10-02T20:15:01-06:00"

}

```



Efecto en inventario: reduce on\_hand y reserved, registra movimiento SALE en stock\_movements.



\### 4. POST /api/v1/inventory/reservations/{id}/release



Request:

```json

{

&#x20; "reason": "CANCELLED",

&#x20; "idempotency\_key": "e7-release-01JXXXX"

}

```



Response (200):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "released",

&#x20; "released\_at": "2026-10-02T20:30:00-06:00"

}

```



\### 5. GET /api/v1/inventory/reservations/{id}



Response (200):

```json

{

&#x20; "reservation\_id": "01JXXXX...",

&#x20; "status": "reserved | consumed | released | expired | rejected",

&#x20; "origin\_reference": "RWD-2026-ABC123",

&#x20; "items": \[],

&#x20; "expires\_at": "..."

}

```



\## Expiracion automatica (evento para Eq.7)



Eq.4 corre un worker cada 5 minutos que:



1\. Detecta reservas E7 vencidas (origin\_system = 'E7' y expires\_at < now)

2\. Las libera (status = RELEASED, release\_reason = EXPIRED)

3\. Publica un evento en integration\_outbox:



```json

{

&#x20; "topic": "inventory.reservation.expired",

&#x20; "payload": {

&#x20;   "reservation\_id": "01JXXXX...",

&#x20;   "origin\_reference": "RWD-2026-ABC123",

&#x20;   "origin\_type": "REWARD",

&#x20;   "expired\_at": "2026-10-02T20:00:00-06:00"

&#x20; },

&#x20; "status": "PENDING"

}

```



Accion sugerida para Eq.7: consumir la coleccion integration\_outbox filtrando por topic = inventory.reservation.expired y status = PENDING, procesar el evento (devolver puntos al usuario, etc.), y marcar el evento como SENT.



Pendiente: definir con Eq.7 si quieren que Eq.4 les notifique via webhook (como Eq.2) o si van a hacer polling sobre integration\_outbox. Hoy solo esta implementada la persistencia en outbox.



\## Errores comunes



| Codigo | Cuando |

|---|---|

| 401 | Firma HMAC invalida |

| 404 | Reserva no encontrada o product\_reference no resuelto |

| 409 | Estado invalido (ya confirmada, ya liberada, sin stock) |

| 422 | Payload invalido |



\## Idempotencia



Misma idempotency\_key -> mismo reservation\_id y mismo stock\_movement\_id. Mismo mecanismo que Eq.3 (indice unico en integration\_idempotency).




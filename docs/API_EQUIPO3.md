# API de Integración — Equipo 4 → Equipo 3

**Documento oficial para consumo de la API de inventario del Equipo 4.**

---

## 1. Información general

| | |
|---|---|
| **Equipo proveedor** | Equipo 4 — Inventario y Suministro |
| **Equipo consumidor** | Equipo 3 — Carrito, Pedido y Checkout |
| **Base URL (Docker)** | `http://campus-eq4:8000/api/equipo4/integration` |
| **Base URL (local)** | `http://127.0.0.1:8000/api/equipo4/integration` |
| **Formato** | JSON (`application/json; charset=utf-8`) |
| **Autenticación** | HMAC-SHA256 |
| **Rate limit** | 120 requests / minuto por IP |

---

## 2. Autenticación (HMAC-SHA256)

**TODOS** los endpoints requieren firma HMAC. Sin firma válida → `401`.

### 2.1 Headers requeridos

```http
X-Team-Timestamp: 1790430658
X-Team-Signature: 8a3f2c9d1b7e4f6a...
Content-Type: application/json; charset=utf-8
```

### 2.2 Cómo calcular la firma

```
1. body_json  = cuerpo del request en JSON compacto (sin espacios extra)
2. body_hash  = SHA256(body_json)  → hex minúsculas
3. payload    = timestamp + "|" + METHOD + "|" + path + "|" + body_hash
4. signature  = HMAC_SHA256(payload, SECRET)  → hex minúsculas
```

**Detalles importantes:**

- **`timestamp`**: Unix epoch en **UTC** (no hora local)
- **`METHOD`**: HTTP method en mayúsculas (`POST`)
- **`path`**: ruta completa **con** slash inicial (`/api/equipo4/integration/availability`)
- **`body_hash`**: SHA256 del body enviado **exactamente** como se envía (sin BOM, sin espacios extra)
- **`SECRET`**: secreto compartido (se entrega por canal seguro, NO por chat público)

### 2.3 ⚠️ REGLA CRÍTICA: timestamp en UTC

**Este error causó fallas en pruebas iniciales.** El timestamp DEBE ser UTC.

**✅ Correcto:**

```php
// PHP
$timestamp = (string) time();  // time() siempre es UTC
```

```javascript
// Node.js
const timestamp = Math.floor(Date.now() / 1000);
```

```python
# Python
import time
timestamp = str(int(time.time()))
```

```bash
# Bash
TIMESTAMP=$(date -u +%s)
```

**❌ Incorrecto:**

```powershell
# PowerShell — USA HORA LOCAL (UTC-6 en México)
$timestamp = Get-Date -UFormat %s  # ← ERROR
```

**Ventana de tolerancia:** ±300 segundos (5 min). Fuera de esto → `401 clock_skew_exceeded`.

---

## 3. Endpoints

### 3.1 `POST /availability` — Consultar disponibilidad

Verifica si hay stock suficiente. **No modifica nada.**

**Request:**

```json
{
  "items": [
    { "product_id": "6ab6e9783c640a880c316c88", "variant_id": null, "quantity": 2 }
  ]
}
```

**Response 200:**

```json
{
  "available": true,
  "items": [
    {
      "product_id": "6ab6e9783c640a880c316c88",
      "variant_id": null,
      "requested": 2,
      "available": 29,
      "has_enough": true
    }
  ]
}
```

**Response 200 (sin stock):**

```json
{
  "available": false,
  "items": [
    {
      "product_id": "6ab6e9783c640a880c316c88",
      "variant_id": null,
      "requested": 100,
      "available": 29,
      "has_enough": false
    }
  ]
}
```

**Notas:**
- Este endpoint es **idempotente** y de **solo lectura**.
- Si un solo item no tiene stock, `available: false`.
- Puede llamarse tantas veces como se necesite.

---

### 3.2 `POST /reservations` — Crear reserva

Aparta stock para una orden. El stock queda en estado `RESERVED` hasta que se confirme o libere.

**Request:**

```json
{
  "order_id": "ORD-12345",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440000",
  "source": "CHECKOUT",
  "expires_at": "2026-09-25T18:00:00Z",
  "items": [
    { "product_id": "6ab6e9783c640a880c316c88", "variant_id": null, "quantity": 2 }
  ]
}
```

**Campos:**

| Campo | Tipo | Requerido | Notas |
|---|---|---|---|
| `order_id` | string | ✅ | ID único de la orden del Eq. 3 |
| `items` | array | ✅ | Lista de productos |
| `items[].product_id` | string | ✅ | ObjectId del producto |
| `items[].variant_id` | string\|null | ⬜ | Null si no hay variantes |
| `items[].quantity` | int | ✅ | Cantidad > 0 |
| `idempotency_key` | string | ✅ | UUID único (ver sección 5) |
| `source` | string | ⬜ | `CHECKOUT` (default), `ORDER`, `REWARD`, `CANJE` |
| `external_reference` | string | ⬜ | Referencia externa libre |
| `expires_at` | ISO date | ⬜ | Default: +2 horas |
| `actor_id` | string | ⬜ | ID del usuario que genera la reserva |

**Response 201:**

```json
{
  "reservation_id": "01M3FMAPTBXVMKX7YEZPE1557V",
  "status": "RESERVED",
  "items": [
    {
      "product_id": "6ab6e9783c640a880c316c88",
      "variant_id": null,
      "location_id": "6ab6e9783c640a880c316c9f",
      "quantity": 2
    }
  ],
  "expires_at": "2026-09-25T18:00:00Z"
}
```

**Response 409 (sin stock):**

```json
{
  "status": "REJECTED",
  "reason": "Existencia insuficiente para producto 6ab6e9783c640a880c316c88."
}
```

**Notas:**
- **Guarda `reservation_id`** en tu orden. Lo vas a necesitar para `confirm` y `release`.
- Si el stock falla a mitad de proceso, la reserva **se compensa completa** (no quedan reservas a medias).
- **`location_id` NO es requerido en el request** — el Eq. 4 lo resuelve automáticamente.

---

### 3.3 `POST /reservations/{id}/confirm` — Confirmar reserva (venta)

Convierte la reserva en una venta definitiva. Descuenta stock físico y genera Kardex.

**Request:**

```json
{
  "order_id": "ORD-12345",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440001"
}
```

**Response 200:**

```json
{
  "reservation_id": "01M3FMAPTBXVMKX7YEZPE1557V",
  "status": "CONFIRMED",
  "movements": [
    {
      "product_id": "6ab6e9783c640a880c316c88",
      "quantity": -2,
      "type": "SALE"
    }
  ]
}
```

**Response 409 (estado incorrecto):**

```json
{
  "message": "Solo se pueden confirmar reservas RESERVED. Actual: RELEASED"
}
```

**Notas:**
- **Llama `confirm` ÚNICAMENTE después de que el pago sea exitoso.**
- Cambia el estado de la reserva a `CONFIRMED`.
- Genera movimientos tipo `SALE` en el Kardex del Eq. 4.

---

### 3.4 `POST /reservations/{id}/release` — Liberar reserva

Devuelve el stock reservado al inventario disponible.

**Request:**

```json
{
  "order_id": "ORD-12345",
  "reason": "CANCELLED_BY_USER",
  "idempotency_key": "550e8400-e29b-41d4-a716-446655440002"
}
```

**Valores sugeridos de `reason`:**
- `CANCELLED_BY_USER` — el cliente canceló
- `PAYMENT_FAILED` — el pago falló
- `EXPIRED` — la reserva expiró
- `MANUAL` — liberación manual

**Response 200:**

```json
{
  "reservation_id": "01M3FMAPTBXVMKX7YEZPE1557V",
  "status": "RELEASED",
  "items_released": [
    {
      "product_id": "6ab6e9783c640a880c316c88",
      "variant_id": null,
      "location_id": "6ab6e9783c640a880c316c9f",
      "quantity": 2
    }
  ]
}
```

**Notas:**
- Libera stock (`reserved -= qty`, `available += qty`).
- **NO toca `on_hand`** (el descuento físico solo ocurre en `confirm`).
- Idempotente: si se llama de nuevo, devuelve el mismo resultado.

---

## 4. Estados de reserva

| Estado | Significado | Cuándo se asigna |
|---|---|---|
| **RESERVED** | Stock apartado para la orden | Al crear con `/reservations` |
| **CONFIRMED** | Venta confirmada, stock físico descontado | Al llamar `/confirm` |
| **RELEASED** | Reserva liberada, stock devuelto | Al llamar `/release` |
| **REJECTED** | Reserva no autorizada (sin stock) | Al intentar crear sin stock |

**No existe `PENDING`.** El flujo va directo a `RESERVED` o `REJECTED`.

---

## 5. Idempotencia

Los endpoints `/reservations`, `/confirm` y `/release` requieren un `idempotency_key` **único por operación lógica**.

### Reglas

- **Formato:** UUID v4 o ULID.
- **Vida útil:** 24 horas.
- **Si reenvías el mismo key:**
  - Con mismo body → **misma respuesta** (sin efectos secundarios).
  - Con body distinto → `409 Conflict`.

### Cómo usarlo en reintentos

```php
$idempotencyKey = (string) Str::uuid();

for ($intento = 1; $intento <= 3; $intento++) {
    try {
        $result = $client->post('/reservations', [
            'order_id' => $orderId,
            'idempotency_key' => $idempotencyKey,  // ← MISMO en cada intento
            'items' => [...],
        ]);
        break;
    } catch (\Exception $e) {
        sleep(1);
    }
}
```

**Recomendación:** genera el key **en el cliente** y reutilízalo en reintentos.

---

## 6. Manejo de errores

### Códigos HTTP

| Código | Significado | Acción del Eq. 3 |
|---|---|---|
| 200 | OK | Procesar normalmente |
| 201 | Created (reserva nueva) | Guardar `reservation_id` |
| 401 | Firma HMAC o timestamp inválido | Corregir timestamp UTC, NO reintentar |
| 409 | Sin stock o conflicto de idempotencia | Mostrar "sin stock" al usuario, NO reintentar |
| 422 | Validación fallida | Corregir payload |
| 500 | Error interno del Eq. 4 | Reportar al Eq. 4 |

### Formato de errores

```json
{ "message": "Descripción del error" }
```

O para reserva sin stock:

```json
{
  "status": "REJECTED",
  "reason": "Existencia insuficiente para producto 6ab6e9783c640a880c316c88."
}
```

### Estrategia de reintentos

| Error | ¿Reintentar? | Estrategia |
|---|---|---|
| 5xx | ✅ Sí | Backoff exponencial (1s, 2s, 4s), mismo idempotency_key |
| 401 | ❌ No | Corregir timestamp UTC (no hora local) |
| 409 | ❌ No | Es definitivo (sin stock o duplicado) |
| Timeout de red | ✅ Sí | Mismo idempotency_key |

---

## 7. Ejemplo completo — Flujo de checkout

```
1. Cliente agrega productos al carrito en el Eq. 3
2. Eq. 3 → POST /availability con items
   → Eq. 4 responde con disponibilidad
3. Si hay stock:
   Eq. 3 → POST /reservations con order_id + items + idempotency_key
   → Eq. 4 responde con reservation_id (status: RESERVED)
   → Eq. 3 guarda reservation_id en la orden
4. Cliente procede al pago (Eq. 2)
5a. Si pago exitoso:
    Eq. 3 → POST /reservations/{id}/confirm
    → Eq. 4 descuenta stock físico y genera Kardex
5b. Si pago falla o cliente cancela:
    Eq. 3 → POST /reservations/{id}/release
    → Eq. 4 devuelve stock a disponible
6. Si Eq. 3 no llama ni confirm ni release:
   → Eq. 4 libera la reserva automáticamente al expirar (default: 2 horas)
```

---

## 8. Ejemplos de código

### 8.1 PHP (Laravel)

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class Team4Client
{
    protected string $baseUrl;
    protected string $secret;

    public function __construct()
    {
        $this->baseUrl = config('services.team4.url');
        $this->secret  = config('services.team4.secret');
    }

    public function post(string $path, array $body): array
    {
        $timestamp = (string) time();
        $json      = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $bodyHash  = hash('sha256', $json);
        $payload   = $timestamp . '|POST|' . $path . '|' . $bodyHash;
        $signature = hash_hmac('sha256', $payload, $this->secret);

        $response = Http::withHeaders([
            'X-Team-Timestamp' => $timestamp,
            'X-Team-Signature' => $signature,
            'Content-Type'     => 'application/json',
        ])
        ->withBody($json, 'application/json')
        ->timeout(5)
        ->post($this->baseUrl . $path);

        return $response->json();
    }
}

// Uso:
$client = new Team4Client();

$result = $client->post('/availability', [
    'items' => [
        ['product_id' => '6ab6e9783c640a880c316c88', 'variant_id' => null, 'quantity' => 2],
    ],
]);
```

### 8.2 Node.js

```javascript
const crypto = require('crypto');
const axios = require('axios');

async function team4Request(path, body) {
  const baseUrl = process.env.TEAM4_API_URL;
  const secret = process.env.TEAM4_SERVICE_SECRET;

  const timestamp = Math.floor(Date.now() / 1000).toString();
  const bodyJson = JSON.stringify(body);
  const bodyHash = crypto.createHash('sha256').update(bodyJson).digest('hex');

  const payload = `${timestamp}|POST|${path}|${bodyHash}`;
  const signature = crypto.createHmac('sha256', secret).update(payload).digest('hex');

  const response = await axios.post(`${baseUrl}${path}`, bodyJson, {
    headers: {
      'X-Team-Timestamp': timestamp,
      'X-Team-Signature': signature,
      'Content-Type': 'application/json; charset=utf-8',
    },
  });

  return response.data;
}

// Uso:
const result = await team4Request('/availability', {
  items: [{ product_id: '6ab6e9783c640a880c316c88', variant_id: null, quantity: 2 }],
});
```

### 8.3 Python

```python
import time
import json
import hmac
import hashlib
import requests

def team4_request(path, body):
    base_url = os.environ['TEAM4_API_URL']
    secret = os.environ['TEAM4_SERVICE_SECRET']

    timestamp = str(int(time.time()))
    body_json = json.dumps(body, separators=(',', ':'))
    body_hash = hashlib.sha256(body_json.encode()).hexdigest()

    payload = f"{timestamp}|POST|{path}|{body_hash}"
    signature = hmac.new(secret.encode(), payload.encode(), hashlib.sha256).hexdigest()

    response = requests.post(
        f"{base_url}{path}",
        data=body_json,
        headers={
            'X-Team-Timestamp': timestamp,
            'X-Team-Signature': signature,
            'Content-Type': 'application/json; charset=utf-8',
        },
    )
    return response.json()

# Uso:
result = team4_request('/availability', {
    'items': [{'product_id': '6ab6e9783c640a880c316c88', 'variant_id': None, 'quantity': 2}],
})
```

### 8.4 cURL

```bash
#!/bin/bash
SECRET="secreto_compartido"
URL="http://campus-eq4:8000/api/equipo4/integration/availability"
PATH_URI="/api/equipo4/integration/availability"
BODY='{"items":[{"product_id":"6ab6e9783c640a880c316c88","variant_id":null,"quantity":1}]}'

TIMESTAMP=$(date -u +%s)
BODY_HASH=$(echo -n "$BODY" | sha256sum | awk '{print $1}')
PAYLOAD="${TIMESTAMP}|POST|${PATH_URI}|${BODY_HASH}"
SIGNATURE=$(echo -n "$PAYLOAD" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST "$URL" \
  -H "X-Team-Timestamp: $TIMESTAMP" \
  -H "X-Team-Signature: $SIGNATURE" \
  -H "Content-Type: application/json" \
  -d "$BODY"
```

---

## 9. Configuración requerida en el Eq. 3

Variables de entorno:

```env
TEAM4_API_URL=http://campus-eq4:8000/api/equipo4/integration
TEAM4_SERVICE_SECRET=<secreto_compartido>
TEAM4_TIMEOUT=5
```

**El secreto compartido se entrega por canal seguro, NO por chat público.**

---

## 10. Contacto y soporte

| | |
|---|---|
| **Líder del Equipo 4** | Jose Armando Arreguin Morales |
| **Repo** | https://github.com/Proyecto-Grupal-API/Proyecto_Grupal (rama `Modulo_4`) |
| **Solicitud origen** | `Solicitud_Requerimiento_03_Integracion_Inventario.docx` |

Para dudas o issues: abrir issue en el repo o contactar directo al líder.

---

*Documento oficial de integración — Equipo 4 · Campus Digital*
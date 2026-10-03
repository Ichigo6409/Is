```markdown

\# Integración con Equipo 1 — Identidad



\*\*Rol de Eq.4\*\*: Cliente (consumimos la API de Eq.1)

\*\*Cliente\*\*: `App\\Services\\Team1Client`

\*\*Consumidor\*\*: `App\\Services\\IdentityService`



\## Contexto



Eq.4 no gestiona usuarios. Los obtiene de Eq.1 (fuente de verdad). Consultamos vía HTTP con HMAC para:



1\. Validar que un `user\_id` existe

2\. Obtener el rol normalizado (`admin`, `inventory\_manager`, `buyer`, `auditor`)

3\. Verificar el scope (`global` o `business`)

4\. Verificar el status (`active` / `suspended` / `inactive`)



\## Modo stub vs real



```

\# Stub (default, mientras Eq.1 no publique)

TEAM1\_STUB\_ENABLED=true



\# Real (cuando Eq.1 publique)

TEAM1\_STUB\_ENABLED=false

TEAM1\_API\_URL=https://eq1.example.com/api

TEAM1\_SERVICE\_SECRET=<secret real>

TEAM1\_TIMEOUT=5

TEAM1\_CACHE\_TTL=60

TEAM1\_ALLOWED\_CLOCK\_SKEW=300

```



Al publicar Eq.1, solo cambian esas variables y todo el sistema lo consume automáticamente.



\## Endpoint esperado de Eq.1



\### GET /users/{userId}



Request con firma HMAC:

```

GET /users/USR-ADMIN-001

X-Team-Timestamp: 1790000123

X-Team-Signature: <hmac-sha256-hex>

```



Response esperada (200):

```json

{

&#x20; "id": "USR-ADMIN-001",

&#x20; "name": "Jose Arreguin",

&#x20; "email": "jose@ejemplo.com",

&#x20; "active": true,

&#x20; "status": "active",

&#x20; "role": {

&#x20;   "name": "admin",

&#x20;   "scope": "global",

&#x20;   "business\_id": null,

&#x20;   "status": "active"

&#x20; }

}

```



Reglas aplicadas por Eq.4:



| Campo | Regla |

|---|---|

| active | Si false, rechazo |

| status | Solo active autoriza |

| role.name | Se normaliza vía config/team1.php |

| role.scope | Default global si no viene |

| role.business\_id | Requerido si scope=business |



Errores manejados:



| Código | Acción de Eq.4 |

|---|---|

| 401 | Log error, rechazo (secret incorrecto) |

| 403 | Log info, rechazo (prohibido) |

| 404 | Log info, rechazo (no existe) |

| 5xx | Retry 100ms → 500ms → 1500ms |



\## Firma HMAC



Igual que los demás equipos, con secreto `TEAM1\_SERVICE\_SECRET`.



Para GET sin body: sha256('') = e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855



\## Retry y resiliencia



| Escenario | Comportamiento |

|---|---|

| 2xx | Devuelve respuesta |

| 4xx | Devuelve inmediato (no reintenta) |

| 5xx | Retry: 100ms → 500ms → 1500ms |

| Timeout o error de red | 3 intentos totales |



Si los 3 intentos fallan, devuelve null → IdentityService interpreta "no autorizado".



\## Caché



Respuestas exitosas se cachean `TEAM1\_CACHE\_TTL` seg (default 60s). Errores NO se cachean.



\## Mapeo de roles



```php

'role\_map' => \[

&#x20;   'admin'             => \['admin', 'administrador', 'owner', 'propietario'],

&#x20;   'inventory\_manager' => \['inventory\_manager', 'manager', 'Responsable de inventario'],

&#x20;   'buyer'             => \['buyer', 'comprador', 'purchaser'],

&#x20;   'auditor'           => \['auditor', 'audit', 'solo\_lectura'],

],

'global\_roles'  => \['admin', 'auditor'],

'allowed\_roles' => \['admin', 'inventory\_manager', 'buyer', 'auditor'],

```



Eq.1 puede mandar el nombre tal cual y Eq.4 lo mapea al canónico.



\## Pendientes con Eq.1



1\. Que publiquen su API con los endpoints y firma descritos

2\. Confirmar el mapeo de sus roles a nuestros 4 canónicos

3\. Definir quién asigna `inventory\_manager` por negocio (¿el Propietario/Gerente de Eq.3?)

```


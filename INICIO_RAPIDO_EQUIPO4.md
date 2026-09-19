# Inicio rápido — Equipo 4

## A. MongoDB local

1. Abrir CMD como administrador y comprobar el servicio:
   `sc query MongoDB`
2. Si está detenido:
   `net start MongoDB`
3. Desde la raíz del proyecto:
   `mongosh "mongodb://127.0.0.1:27017" database/mongo/team4_init.js`
4. Cargar datos:
   `mongosh "mongodb://127.0.0.1:27017" database/mongo/team4_seed.js`
5. Verificar:
   `mongosh`
   `use campus_digital`
   `show collections`

## B. Laravel

1. `composer install`
2. Copiar `.env.example` a `.env`
3. `php artisan key:generate`
4. Confirmar extensión PHP MongoDB:
   `php -m | findstr mongodb`
5. `npm install`
6. `npm run dev`
7. En otra terminal:
   `php artisan serve`

## C. Variables .env

DB_CONNECTION=mongodb
DB_URI=mongodb://127.0.0.1:27017
DB_DATABASE=campus_digital
TEAM_SERVICE_SHARED_SECRET=<secreto local, no subir a GitHub>

## D. Atlas

Crear un cluster gratuito en MongoDB Atlas, crear un usuario de base de datos y agregar la IP pública de cada compañero a la Access List. En cada `.env` usar la URI `mongodb+srv://...` y el mismo `DB_DATABASE=campus_digital`.

## E. GitHub

No subir `.env`, `vendor/`, `node_modules/`, dumps con credenciales ni datos privados. Sí subir código, `database/mongo/*.js`, documentación y `.env.example`.

## F. Devoluciones

La interfaz `/equipo4/devoluciones` muestra ambos tipos. Los contratos:
POST /api/equipo4/integration/supplier-returns
POST /api/equipo4/integration/customer-returns

requieren firma HMAC según `docs/SEGURIDAD_Y_MICROSERVICIOS.md`.

## G. Roles

Mínimos: administrador y alumno. En producción Equipo 1 proporciona identidad/autenticación/rol definitivo. Equipo 4 no debe crear una segunda fuente de verdad de usuarios.

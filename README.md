# Campus Digital — Equipo 4

Proyecto Laravel 13 + Inertia.js + Vue 3 + Vite con el mismo maquetado del ZIP anterior.

## Requisitos
- PHP 8.3+
- Composer
- Node.js 20+
- npm

## Instalación en Windows
```powershell
composer install
copy .env.example .env
php artisan key:generate
npm install
npm run dev
```

En otra terminal:
```powershell
php artisan serve
```

Abrir:
http://127.0.0.1:8000/equipo4/

## Rutas del maquetado
- /equipo4/
- /equipo4/inventario
- /equipo4/productos
- /equipo4/almacenes
- /equipo4/kardex
- /equipo4/proveedores
- /equipo4/compras
- /equipo4/recepciones
- /equipo4/alertas
- /equipo4/conteos
- /equipo4/souvenirs

Este paquete conserva el maquetado visual y funcional entregado previamente; no incluye todavía persistencia real ni autenticación.

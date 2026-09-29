#!/bin/bash
set -e

echo "==========================================="
echo "  Campus Digital - Equipo 4"
echo "  Iniciando contenedor..."
echo "==========================================="

cd /var/www/html

# Crear .env vacio si no existe (Laravel lo pide)
if [ ! -f .env ]; then
    echo "Creando .env base..."
    touch .env
fi

# Asegurar permisos
chown -R www-data:www-data storage bootstrap/cache

# Limpiar caches viejas
echo "Limpiando caches..."
php artisan config:clear || true
php artisan route:clear || true
php artisan view:clear || true
php artisan cache:clear || true

echo "==========================================="
echo "  Contenedor listo. Arrancando servicios."
echo "==========================================="

exec "$@"

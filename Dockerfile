# ============================================================
# STAGE 1: Build del frontend (Vite)
# ============================================================
FROM node:20-alpine AS frontend-builder

WORKDIR /app

COPY package*.json ./
RUN npm install

COPY vite.config.js ./
COPY tailwind.config.js* ./
COPY postcss.config.js* ./
COPY resources ./resources
COPY public ./public

RUN npm run build

# ============================================================
# STAGE 2: Aplicacion (PHP-FPM + Nginx + Supervisor)
# ============================================================
FROM php:8.4-fpm-bookworm

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libzip-dev libpng-dev libonig-dev libxml2-dev \
    libssl-dev pkg-config \
    nginx supervisor \
    && rm -rf /var/lib/apt/lists/*

# Extensiones PHP
RUN docker-php-ext-install pdo mbstring bcmath gd zip intl opcache

# Extension MongoDB
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Composer primero (cache de capas)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Codigo completo
COPY . .

# Build del frontend (stage 1)
COPY --from=frontend-builder /app/public/build ./public/build

# Configuracion de Docker
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Autoload optimizado
RUN composer dump-autoload --optimize --no-dev

# Carpetas de storage + permisos
RUN mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache/data storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

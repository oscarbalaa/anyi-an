# syntax=docker/dockerfile:1

# 1) Compila React
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
ARG AYNI_BASE=/
RUN AYNI_BASE=$AYNI_BASE npm run build

# 2) Apache + PHP 8.1, igual que el hosting del curso
FROM php:8.1-apache-bookworm AS base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql zip gd intl bcmath opcache exif \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/app/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/app/php.ini /usr/local/etc/php/conf.d/ayni.ini
RUN echo "ServerName localhost" > /etc/apache2/conf-enabled/servername.conf \
    && echo "ServerTokens Prod\nServerSignature Off" > /etc/apache2/conf-enabled/seguridad-ayni.conf
WORKDIR /var/www/ayni

# 3) Modo normal: código ya construido dentro de la imagen
FROM base AS produccion
COPY backend/composer.json backend/composer.lock backend/
RUN cd backend && composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY .htaccess ./
COPY backend/ backend/
COPY --from=frontend /app/dist/ backend/public/
RUN cd backend \
    && touch .env \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs storage/app/public \
    && chown -R www-data:www-data storage bootstrap/cache
HEALTHCHECK --interval=10s --timeout=5s --start-period=20s --retries=12 \
    CMD curl -fsS http://localhost/api/v1/health > /dev/null || exit 1

# 4) Pruebas del backend (incluye dependencias de desarrollo)
FROM base AS pruebas
COPY backend/composer.json backend/composer.lock backend/
RUN cd backend && composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress
COPY backend/ backend/
# La configuración llega por variables de entorno; el .env vacío evita avisos de dotenv
RUN cd backend && touch .env && composer dump-autoload && php artisan package:discover
WORKDIR /var/www/ayni/backend
CMD ["php", "artisan", "test"]

# 5) Modo desarrollo: el código se monta desde la carpeta del proyecto
FROM base AS desarrollo
CMD ["sh", "-c", "cd backend && composer install --no-interaction && chown -R www-data:www-data storage bootstrap/cache && apache2-foreground"]

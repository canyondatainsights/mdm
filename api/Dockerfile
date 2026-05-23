# Laravel API (PHP 8.4). Baseline image using the built-in server; for higher
# throughput swap the CMD for FrankenPHP/Octane or nginx + php-fpm.
FROM php:8.4-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev libzip-dev unzip git \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction

EXPOSE 8000
# Migrate + (idempotently) ingest on boot, then serve.
CMD php artisan migrate --force \
    && php artisan kb:ingest \
    && php artisan serve --host=0.0.0.0 --port=8000

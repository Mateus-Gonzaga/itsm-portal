# Imagem de PRODUÇÃO do portal (PHP-FPM + extensões do Laravel/PostgreSQL).
# PHP 8.4 para casar com o composer.lock gerado no dev.
FROM php:8.4-fpm-alpine

# Extensões necessárias: pdo_pgsql (banco), bcmath, mbstring, zip, opcache.
RUN apk add --no-cache git libpq-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath mbstring zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia o código (em produção o deploy.sh roda composer install no container).
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction || true \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

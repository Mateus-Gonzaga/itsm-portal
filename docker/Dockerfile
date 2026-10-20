# Imagem de PRODUÇÃO do portal (PHP-FPM + extensões do Laravel/PostgreSQL).
# PHP 8.4 para casar com o composer.lock gerado no dev.
FROM php:8.4-fpm-alpine

# Extensões necessárias: pdo_pgsql (banco), bcmath, mbstring, zip, opcache.
RUN apk add --no-cache git libpq-dev oniguruma-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath mbstring zip opcache

# Redis (cache do portal) via extensão nativa phpredis.
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Padrões de recursos (perfil VM pequena) — o docker-compose/.env pode sobrescrever.
# Ficam na imagem para o PHP nunca ler esses valores vazios (nem durante o build).
ENV PHP_FPM_PM=ondemand \
    PHP_FPM_MAX_CHILDREN=6 \
    PHP_FPM_START_SERVERS=2 \
    PHP_FPM_MIN_SPARE=1 \
    PHP_FPM_MAX_SPARE=3 \
    PHP_OPCACHE_MB=64 \
    PHP_MEMORY_LIMIT=192M

# OPcache de produção (código pré-compilado em memória).
COPY docker/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini

# PHP-FPM sob demanda (encerra processos ociosos — economiza RAM).
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zz-pool.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copia o código (em produção o deploy.sh roda composer install no container).
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction || true \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

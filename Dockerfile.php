FROM php:8.2-fpm-alpine

# Установить composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Установить другие зависимости
RUN apk add --no-cache postgresql-dev libzip-dev icu-dev libsodium-dev && \
    docker-php-ext-install pdo_pgsql intl zip sodium opcache
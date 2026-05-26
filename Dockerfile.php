FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apk add --no-cache postgresql-dev libzip-dev icu-dev libsodium-dev \
    && docker-php-ext-install pdo_pgsql intl zip sodium opcache

RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/memory-limit.ini

CMD ["php-fpm"]
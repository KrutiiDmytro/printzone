FROM php:8.2-fpm-alpine

WORKDIR /var/www/html

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql intl zip sodium opcache

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/memory-limit.ini

CMD ["php-fpm"]
FROM php:8.2-fpm

WORKDIR /var/www/html

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_pgsql intl zip sodium opcache amqp

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN printf "memory_limit=512M\n" > /usr/local/etc/php/conf.d/memory-limit.ini

CMD ["php-fpm"]
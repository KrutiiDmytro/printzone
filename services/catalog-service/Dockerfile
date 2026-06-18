FROM dunglas/frankenphp:1-php8.2

WORKDIR /app

RUN install-php-extensions pdo_pgsql intl zip opcache sodium amqp

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN printf "memory_limit=512M\n" > "$PHP_INI_DIR/conf.d/memory-limit.ini"

# HTTP only on :80 (no TLS in dev); mapped to 8002 on the host.
ENV SERVER_NAME=":80"

# App source and vendor are bind-mounted in dev (see compose.yaml); run
# `composer install` once before `up`. FrankenPHP serves /app/public by default.

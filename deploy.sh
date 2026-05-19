#!/bin/bash
set -e

APP_DIR="/var/www/app"
DOMAIN="e-commerce.it.com"
COMPOSE="docker compose -f compose.yaml -f compose.prod.yaml"

echo "=== 1. Installing Docker ==="
if ! command -v docker &> /dev/null; then
    apt-get update -q
    apt-get install -y docker.io docker-compose-plugin
    systemctl enable docker
    systemctl start docker
    echo "Docker installed."
else
    echo "Docker already installed."
fi

echo "=== 2. Opening firewall ports ==="
if command -v ufw &> /dev/null; then
    ufw allow 22
    ufw allow 80
    ufw allow 443
    ufw --force enable
fi

echo "=== 3. Creating .env.local ==="
cd "$APP_DIR"

if [ ! -f .env.local ]; then
    APP_SECRET=$(openssl rand -hex 32)
    POSTGRES_PASSWORD=$(openssl rand -hex 16)

    cat > .env.local <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=${APP_SECRET}

POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
POSTGRES_USER=app
POSTGRES_DB=app

DATABASE_URL="postgresql://app:${POSTGRES_PASSWORD}@database:5432/app?serverVersion=16&charset=utf8"
DATABASE_REPLICA_URL="postgresql://app:${POSTGRES_PASSWORD}@database-replica:5432/app?serverVersion=16&charset=utf8"

CORS_ALLOW_ORIGIN=^https?://e-commerce\\.it\\.com$

STORAGE_TYPE=local
EOF
    echo ".env.local created with generated secrets."
else
    echo ".env.local already exists, skipping."
fi

# Export vars from .env.local so docker compose uses them for ${VAR:-default} substitution
set -a
# shellcheck disable=SC1091
source .env.local
set +a

echo "=== 4. Building and starting containers ==="
$COMPOSE build php
$COMPOSE up -d database database-replica

echo "Waiting for primary database..."
until $COMPOSE exec -T database pg_isready -U app -d app -q 2>/dev/null; do
    sleep 3
done
echo "Primary ready."

echo "Waiting for replica database (pg_basebackup may take a minute)..."
ATTEMPTS=0
until $COMPOSE exec -T database-replica pg_isready -U app -d app -q 2>/dev/null; do
    ATTEMPTS=$((ATTEMPTS + 1))
    if [ $ATTEMPTS -ge 40 ]; then
        echo "WARNING: Replica not ready after 2 minutes, proceeding anyway..."
        break
    fi
    sleep 3
done
echo "Replica ready."

echo "=== 5. Installing dependencies ==="
$COMPOSE run --rm php composer install --no-dev --optimize-autoloader --no-interaction

echo "=== 6. Running migrations ==="
$COMPOSE run --rm php php bin/console doctrine:migrations:migrate --no-interaction

echo "=== 7. Generating JWT keys ==="
$COMPOSE run --rm php php bin/console lexik:jwt:generate-keypair --overwrite --no-interaction

echo "=== 8. Getting SSL certificate ==="
chmod +x docker/nginx/init-letsencrypt.sh
./docker/nginx/init-letsencrypt.sh

echo "=== 9. Starting all services ==="
$COMPOSE up -d

echo ""
echo "=== DONE ==="
echo "Site: https://$DOMAIN"

#!/bin/bash
set -e

APP_DIR="/var/www/app"
DOMAIN="e-commerce.it.com"
COMPOSE="$COMPOSE --env-file .env.local"

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

cat > .env.local <<'EOF'
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=22f3533d6c51f660d8467e5b87229c7588fae6b4709ee9b2e9b87c235a370e75

POSTGRES_PASSWORD=62aaba0c934c5d087d8f3b078feb049c
POSTGRES_USER=app
POSTGRES_DB=app

DATABASE_URL="postgresql://app:62aaba0c934c5d087d8f3b078feb049c@database:5432/app?serverVersion=16&charset=utf8"
DATABASE_REPLICA_URL="postgresql://app:62aaba0c934c5d087d8f3b078feb049c@database-replica:5432/app?serverVersion=16&charset=utf8"

CORS_ALLOW_ORIGIN=^https?://e-commerce\.it\.com$

STORAGE_TYPE=local
EOF

echo ".env.local created."

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

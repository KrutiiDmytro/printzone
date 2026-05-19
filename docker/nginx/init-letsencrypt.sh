#!/bin/bash
set -e

DOMAIN="e-commerce.it.com"
EMAIL="krutiidmytro@gmail.com"
COMPOSE="docker compose -f compose.yaml -f compose.prod.yaml"

# Create dummy cert inside the letsencrypt Docker volume so nginx can start
echo "Creating temporary self-signed certificate in volume..."
$COMPOSE run --rm --entrypoint sh certbot -c "
    mkdir -p /etc/letsencrypt/live/$DOMAIN &&
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout /etc/letsencrypt/live/$DOMAIN/privkey.pem \
        -out /etc/letsencrypt/live/$DOMAIN/fullchain.pem \
        -subj '/CN=$DOMAIN' 2>/dev/null &&
    echo 'Dummy cert created.'
"

# Start nginx (now it can find the cert)
echo "Starting nginx..."
$COMPOSE up -d nginx
sleep 5

# Request real certificate via webroot challenge
echo "Requesting Let's Encrypt certificate..."
$COMPOSE run --rm --entrypoint certbot certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email "$EMAIL" \
    --agree-tos \
    --no-eff-email \
    -d "$DOMAIN"

if [ $? -eq 0 ]; then
    echo "Certificate obtained. Reloading nginx..."
    $COMPOSE exec nginx nginx -s reload
    echo "Done. Site is live at https://$DOMAIN"
else
    echo "ERROR: Failed to obtain certificate. Check that:"
    echo "  1. Domain $DOMAIN points to this server's IP"
    echo "  2. Port 80 is open in the firewall"
    exit 1
fi

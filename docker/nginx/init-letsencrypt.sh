#!/bin/bash
# Run once on first deployment to obtain SSL certificate from Let's Encrypt.
# After this script completes, switch nginx to default.conf and restart.

DOMAIN="e-commerce.it.com"
EMAIL="krutiidmytro@gmail.com"
CERT_PATH="/etc/letsencrypt/live/$DOMAIN"
COMPOSE="docker compose -f compose.yaml -f compose.prod.yaml --env-file .env.local"

# Create dummy certs so nginx can start and serve the ACME challenge
if [ ! -d "$CERT_PATH" ]; then
    echo "Creating temporary self-signed certificate..."
    mkdir -p "$CERT_PATH"
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "$CERT_PATH/privkey.pem" \
        -out "$CERT_PATH/fullchain.pem" \
        -subj "/CN=$DOMAIN" 2>/dev/null
fi

# Start nginx with SSL (uses dummy cert initially)
echo "Starting nginx..."
$COMPOSE up -d nginx

# Wait for nginx to be ready
sleep 5

# Request real certificate via webroot
echo "Requesting Let's Encrypt certificate..."
$COMPOSE run --rm certbot certonly \
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

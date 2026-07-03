# Order Service

Owns the **Order** bounded context extracted from the PrintZone monolith
(Strangler Fig step 3): orders, the checkout Saga, and Stripe payments.

## Responsibilities
- `POST /api/checkout` — create an `Order` (PENDING) from line items posted by the
  monolith, open a Stripe Checkout session, return its redirect URL.
- `POST /stripe/webhook` — verify the Stripe signature; mark orders `PAID` / `FAILED`.
- `GET /api/orders`, `GET /api/orders/{id}` — read API for the monolith admin + Export.
- Publishes `order.OrderCreated` / `order.OrderPaid` / `order.OrderCancelled` to
  RabbitMQ via the transactional outbox + `order-relay` worker. The Catalog Saga
  reserves/releases stock; the monolith sends the email receipt.

## Data
Own PostgreSQL database (`order_service`): tables `orders`, `order_items`, `outbox`.
Cross-service references (`user_id`, `product_id`) are UUIDs with no FK;
`user_email`, `product_name`, `price` are snapshots.

## Run (dev)
```bash
docker compose up -d --build
docker compose run --rm --no-deps order-service composer install
docker compose exec order-service php bin/console doctrine:migrations:migrate -n
curl localhost:8003/health/ready
```

## Test
```bash
docker compose exec order-service vendor/bin/phpunit
```

Auth: validates RS256 service tokens signed by the monolith with the shared
`config/jwt` keypair (mounted read-only). Stripe authenticates the webhook via its
signed payload, not a JWT.

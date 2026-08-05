# Cart Service

Owns the **persistent cart of authenticated users**, extracted from the PrintZone
monolith (Strangler Fig step 4). Guest carts stay in the monolith's Symfony session;
this service holds the cart once a user logs in.

## Responsibilities
- `GET /api/carts/{userId}` — the user's cart lines (snapshots: `productId`, `productName`, `price`, `quantity`).
- `POST /api/carts/{userId}/items` — add/upsert a line (repeated products sum quantity).
- `PATCH /api/carts/{userId}/items/{productId}` — set quantity (`0` removes the line).
- `DELETE /api/carts/{userId}/items/{productId}` — remove a line.
- `DELETE /api/carts/{userId}` — clear the cart.

The monolith is the only caller: it resolves products via the Catalog Service and
sends name/price snapshots here, so this service never talks to Catalog itself.

## Data
Own PostgreSQL database (`cart_service`): tables `carts`, `cart_items`. Cross-service
references (`user_id`, `product_id`) are UUIDs with no FK; `product_name`, `price`
are snapshots taken when the item was added. Carts are created at runtime — no fixtures.

## Run (dev)
```bash
docker compose up -d --build
docker compose exec cart-service composer install
docker compose exec cart-service php bin/console doctrine:migrations:migrate -n
curl localhost:8004/health/ready
```

## Test
```bash
docker compose exec cart-service vendor/bin/phpunit
```

Auth: validates RS256 service tokens signed by the monolith with the shared
`config/jwt` keypair (mounted read-only). Reads need any valid service token;
writes (`POST`/`PATCH`/`DELETE`) require `ROLE_CART_ADMIN`.

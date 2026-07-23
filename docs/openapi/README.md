# OpenAPI Specifications — Core Services

Machine-readable API contracts for the four core PrintZone services. Each spec is an
OpenAPI 3.1.0 document that lives next to the service it describes and is hand-maintained
against that service's controllers.

| Service | Port | Spec |
|---------|------|------|
| User    | 8001 | [`services/user-service/openapi.yaml`](../../services/user-service/openapi.yaml) |
| Catalog | 8002 | [`services/catalog-service/openapi.yaml`](../../services/catalog-service/openapi.yaml) |
| Order   | 8003 | [`services/order-service/openapi.yaml`](../../services/order-service/openapi.yaml) |
| Cart    | 8004 | [`services/cart-service/openapi.yaml`](../../services/cart-service/openapi.yaml) |

## View them (Swagger UI)

`index.html` here loads all four specs via Swagger UI (from CDN). Because browsers block
`fetch()` from `file://` pages, serve the repo over HTTP and open the page from there:

```bash
# from the repository root
python -m http.server 9000
# then open:  http://localhost:9000/docs/openapi/index.html
```

Switch between services with the dropdown in the top-right corner.

## Validate

```bash
npx @redocly/cli@latest lint services/user-service/openapi.yaml
npx @redocly/cli@latest lint services/catalog-service/openapi.yaml
npx @redocly/cli@latest lint services/order-service/openapi.yaml
npx @redocly/cli@latest lint services/cart-service/openapi.yaml
```

## Conventions

- **Auth** — all `/api/*` routes require a service-to-service RS256 JWT
  (`Authorization: Bearer <token>`) signed with the shared Lexik keypair. Public routes:
  `/health/*`, and User Service `/api/auth/{login,register}`. Elevated write roles
  (`ROLE_CATALOG_ADMIN`, `ROLE_ADMIN`, `ROLE_CART_ADMIN`) are noted per operation.
- **Money** — all prices/totals are integer **cents**.
- **Ids** — UUID (`format: uuid`); timestamps are ISO-8601 (`format: date-time`).
- **Errors** — `{ "error": "<message>" }`.

These specs are documentation artifacts; they are not served by the services at runtime and
do not change any service behaviour.

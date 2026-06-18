# Catalog Service (Phase 4 — Strangler Fig extraction)

Second microservice extracted from the PrintZone monolith. Owns the **catalog
read model**: products, categories, brands. Standalone Symfony 7.4 app
(FrankenPHP), own PostgreSQL database (`db-catalog`), runs on **:8002**.

In this MVP it exposes a **read API**; one monolith consumer (the Export module's
`ProductExtractor`) reads products from it over HTTP instead of local Doctrine.
The storefront, cart rendering and admin still read catalog from the monolith
(deferred to later sub-phases).

## Service-to-service auth

The API is JWT-protected. Callers present a token signed with the **shared RS256
keypair** (the monolith's `config/jwt/` is mounted read-only at `/jwt`). The
service only **verifies** signatures (it never issues tokens) and reconstructs
the caller from the verified claims — there is no user database here.

## API

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/api/products` | service JWT | List products; filters `category,priceMin,priceMax,stockMin,stockMax,isFeatured`; paging `page,limit` (max 500) → `{data,page,limit,total}` |
| GET | `/api/products/{id}` | service JWT | Product detail (404 if missing) |
| GET | `/api/categories` | service JWT | Category list |
| GET | `/health/live` | public | Liveness |
| GET | `/health/ready` | public | Readiness (DB ping) |

## Run

```bash
cd services/catalog-service
docker compose build catalog-service
docker compose run --rm --no-deps catalog-service composer install
docker compose run --rm --no-deps catalog-service php bin/console doctrine:migrations:migrate -n
docker compose run --rm --no-deps catalog-service php bin/console doctrine:fixtures:load -n
docker compose up -d
# read API needs a token signed by the shared keypair (e.g. from user-service login)
curl localhost:8002/health/ready
```

> **Windows/Docker note.** `vendor/` and `var/` are on named volumes (not the bind
> mount) — see user-service README for why. Run `composer install` again after
> dependency changes.

## Known transition compromise

`db-catalog` is seeded from fixtures and is independent of the monolith's catalog
tables. The admin still writes catalog data to the **monolith**, so the service's
copy can drift. Acceptable for the point-in-time Export read; closes when the
admin/storefront move onto the service.

## Out of scope (later phases)

`stock_reservations` (needs the Order Saga — Phase 5), PrinterModel / product
attributes, write API + admin, storefront cutover, API Gateway.

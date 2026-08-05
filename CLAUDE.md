# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# IMPORTANT

1. Before writing any code, describe your approach and wait for approval.
2. If requirements are ambiguous, ask clarifying questions before writing code.
3. After finishing code, list edge cases and suggest test cases.
4. If a task requires changes to more than 3 files, stop and break it into smaller tasks.
5. When there's a bug, start by writing a test that reproduces it, then fix it.
6. Every time the user corrects you, reflect what went wrong and plan to prevent it.

# Core Principles

1. Simplicity First: Make every change as simple as possible. Compact minimal code.
2. No Laziness: Find root causes. No temporary fixes. Senior developer standards.
3. Minimal Impact: Changes should only touch what's necessary. Avoid introducing bugs.

# Task Management

1. Plan First: Write plan to `./docs/todo.md` with checkable items
2. Verify plan: Check in before starting implementation
3. Track Progress: Mark items complete as you go
4. Explain Changes: High-level summary at each step
5. Document Results: Add review section to `./docs/todo.md`
6. Capture Lessons: Update `./docs/todo.md` after corrections

## Project Overview

Symfony 7.4 print-shop e-commerce application (PrintZone) with PHP 8.2+, PostgreSQL 16, and AWS S3 file storage abstraction via League Flysystem. Uses API Platform for REST endpoints and EasyAdmin for the admin interface.

## Development Environment

All commands run inside Docker containers:

```bash
docker compose build php
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

Services: `php` (PHP 8.2-FPM), `database` (PostgreSQL 16 primary), `database-replica` (PostgreSQL 16 read replica), `nginx`, `mailpit`.

After adding a new Twig extension, run `php bin/console cache:clear` — otherwise the function won't be visible in templates even though the service is registered.

After any `composer update` that touches a bundle shipping frontend assets (EasyAdmin, API Platform), run `php bin/console assets:install public`. EasyAdmin loads its CSS/JS via AssetMapper at digest paths (`/bundles/easyadmin/app.<hash>.css`). Updating the bundle changes the asset content → new hash, but `public/bundles/easyadmin/` keeps the stale copy, so the page requests a hash that 404s → admin renders with no CSS (giant unstyled images). `assets:install` re-copies fresh assets matching the current digests.

## Testing

```bash
# Run all tests
docker compose exec php php bin/phpunit

# Run a specific test suite
docker compose exec php php bin/phpunit --testsuite Unit
docker compose exec php php bin/phpunit --testsuite Functional

# Run a specific directory or file
docker compose exec php php bin/phpunit tests/Unit/Storage/
docker compose exec php php bin/phpunit tests/Functional/Admin/
```

Test suites are defined in `phpunit.dist.xml`. Use `.env.test` for test environment — it uses an in-memory SQLite DB.

## Migrations

```bash
# Generate a migration after changing an entity
docker compose exec php php bin/console doctrine:migrations:diff --no-interaction

# Apply pending migrations
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Validate schema matches mapping
docker compose exec php php bin/console doctrine:schema:validate
```

**Important:** Never run `doctrine:migrations:diff` more than once before applying. Running it multiple times in parallel generates duplicate migration files that all try to create the same table.

## Environment Variables

Key variables in `.env` / `.env.local`:

```
STORAGE_TYPE=local          # or "s3" — controls FileStorageFactory backend
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=eu-north-1
AWS_S3_BUCKET=
DATABASE_URL=postgresql://app:changeme@database:5432/app?serverVersion=16&charset=utf8
DATABASE_REPLICA_URL=postgresql://app:changeme@database-replica:5432/app?serverVersion=16&charset=utf8
```

Full setup guide for S3 is in `docs/STORAGE_SETUP.md`.

## Architecture

### Module Structure (`src/`)

Modular monolith with DDD conventions. Each domain module owns its entities under `Domain/Entity/`:

- **Catalog** — `Product`, `Category`, `Brand`, `ProductAttribute`; domain in `Catalog/Domain/Entity/`
- **Cart** — `Cart`, `CartItem` + `CartService`; domain in `Cart/Domain/Entity/`
- **Order** — `Order`, `OrderItem` with status lifecycle (PENDING → PAID → PROCESSING → SHIPPED → DELIVERED)
- **User** — `User` entity with OAuth integration (Google, GitHub)
- **Export** — `ExportJob` entity, async export via Symfony Messenger; formatters (CSV/JSON/XML), extractors (Product/Order/User)
- **Storage** — File storage abstraction (local or S3)

Repositories all live flat in `src/Repository/` regardless of which domain they belong to.

### Doctrine Entity Mapping

Entities are NOT auto-discovered. Each module is explicitly mapped in `config/packages/doctrine.yaml`:

```yaml
mappings:
  Catalog: { dir: src/Catalog/Domain/Entity, prefix: App\Catalog\Domain\Entity }
  User:    { dir: src/User/Domain/Entity,    prefix: App\User\Domain\Entity }
  Order:   { dir: src/Order/Domain/Entity,   prefix: App\Order\Domain\Entity }
  Cart:    { dir: src/Cart/Domain/Entity,    prefix: App\Cart\Domain\Entity }
  Export:  { dir: src/Export/Domain/Entity,  prefix: App\Export\Domain\Entity }
```

When adding a new entity in a new module, add it here. Entities in existing modules are picked up automatically.

**Per-service schemas (Phase 2.2).** Each module's tables live in their own PostgreSQL schema, set via `#[ORM\Table(name: '...', schema: '...')]`: `users`, `catalog`, `cart`, `orders`, `exports`, `messaging`. Infra tables (`messenger_messages`, `doctrine_migration_versions`) stay in `public`. There are no cross-schema FKs. A new entity must declare the `schema:` matching its module.

⚠️ **SQLite tests + schemas:** ORM 3.6 dropped schema emulation from `DefaultQuoteStrategy::getTableName` (it emits `catalog.products`, which SQLite reads as `database.table` → "no such table"), while DBAL's SchemaTool still emulates DDL as `catalog__products`. `src/Doctrine/SchemaEmulatingQuoteStrategy.php` (wired via `orm.quote_strategy`) re-aligns DML on platforms without native schema support; it is a no-op on PostgreSQL. Don't remove it or schema-qualified entities break in tests.

### Storage Module (`src/Storage/`)

- `FileStorageInterface` — defines `write`, `read`, `delete`, `exists`, `listKeys`, `publicUrl`
- `FlysystemFileStorage` — implementation backed by League Flysystem
- `FileStorageFactory` — selects local or S3 adapter based on `STORAGE_TYPE` env var

`FileStorageFactory` is excluded from Symfony autowiring — manually wired in `services.yaml`. Images served via `GET /media?key=...` (MediaController). EasyAdmin uploads use presigned S3 URLs from `ProductImagePresignService`; frontend logic in `public/js/admin-product-image-s3.js`.

### Twig Extensions (`src/Twig/`)

Global Twig functions available in all templates (auto-registered via `autoconfigure: true`):

| Extension | Function | Returns |
|-----------|----------|---------|
| `BrandExtension` | `all_brands()` | All `Brand[]` ordered by name |
| `CategoryExtension` | `get_categories()` | Root `Category[]` ordered by name |
| `CartExtension` | — | Cart item count / totals |
| `ProductImageExtension` | — | Image URL helpers |

### Database

- Reads go to the replica (`database-replica`), writes to the primary (`database`) — handled automatically by Doctrine's `PrimaryReadReplicaConnection`.
- If the replica is down, Doctrine throws on the first SELECT. Restart with `docker compose up -d database-replica`.

### Authentication

- JWT (LexikJWT) for API endpoints (`/api/*`)
- Session + OAuth 2.0 (Google, GitHub) for web
- Roles: `ROLE_USER`, `ROLE_ADMIN`

### Key Config Files

| File | Purpose |
|------|---------|
| `config/packages/doctrine.yaml` | Entity namespace mappings + replica connection |
| `config/packages/storage.yaml` | FileStorageFactory wiring (local/S3 backends) |
| `config/packages/aws.yaml` | S3Client credentials and region |
| `config/packages/security.yaml` | Auth, JWT, role hierarchy |
| `config/packages/messenger.yaml` | Async export queue routing |
| `config/services.yaml` | Manual service wiring, admin upload directory |

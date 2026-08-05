# User Service (Phase 3 — Strangler Fig extraction)

First microservice extracted from the PrintZone monolith. Owns **identity**:
registration, login, JWT issuance, user profiles. Standalone Symfony 7.4 app
(FrankenPHP), own PostgreSQL database (`db-user`), runs on **:8001**.

## Why the monolith trusts it (MVP)

Tokens are signed with the **same RS256 keypair** as the monolith (the monolith's
`config/jwt/` is mounted read-only at `/jwt`). The monolith validates incoming
JWTs with that public key, so a token issued here is accepted there. The `sub`/
`username` claim is the email, which the monolith resolves against its own user
provider (same fixture users during the transition).

Verify the cross-trust without HTTP:

```bash
# token from the service
TOKEN=$(curl -s -X POST http://localhost:8001/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin123"}' | jq -r .token)

# signature validates against the monolith's public key
H=$(cut -d. -f1 <<<"$TOKEN"); P=$(cut -d. -f2 <<<"$TOKEN"); S=$(cut -d. -f3 <<<"$TOKEN")
printf '%s' "$H.$P" > si; printf '%s' "$S" | tr '_-' '/+' | base64 -d > sig
openssl dgst -sha256 -verify config/jwt/public.pem -signature sig si   # -> Verified OK
```

## API

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/auth/register` | public | Create account `{email, password, fullName}` |
| POST | `/api/auth/login` | public | Issue JWT `{email, password}` → `{token}` |
| GET  | `/api/users` | ROLE_ADMIN | List users |
| GET  | `/api/users/{id}` | self or ADMIN | User profile |
| GET  | `/health/live` | public | Liveness |
| GET  | `/health/ready` | public | Readiness (DB ping) |

## Run

```bash
cd services/user-service
docker compose build user-service
docker compose run --rm --no-deps user-service composer install
docker compose run --rm --no-deps user-service php bin/console doctrine:migrations:migrate -n
docker compose run --rm --no-deps user-service php bin/console doctrine:fixtures:load -n
docker compose up -d
curl localhost:8001/health/ready
```

> **Windows/Docker note.** `vendor/` and `var/` are mapped to named volumes
> (not the bind mount). Without that, tens of thousands of autoload `stat()`
> calls over the 9p/virtiofs bind mount make every request exceed PHP's
> `max_execution_time`. After dependency changes run `composer install` again.

## Out of scope (later Strangler phases)

OAuth (Google/GitHub) migration, monolith web/admin session cutover, removing
the monolith's `users` table, API Gateway. The monolith is currently unchanged.

# Фаза 7 (крок 3) — Export Service

> Активний робочий план. Попередні плани фаз (Storage — MR !32; Notification та ін.) — в git-історії.

## Мета

Виокремити **Export Service** (`:8009`) — асинхронний сервіс генерації звітів (CSV/JSON/XML)
по продуктах/замовленнях/користувачах. Останнє винесення Phase 7. Власна БД (`export_jobs`),
власний worker; дані тягне по HTTP з catalog/order/user, результат пише в **storage-service**
(вже винесений), лист про завершення шле сам (Mailer).

## Що вже є (заземлення)

Модуль `src/Export/` уже майже сервіс-орієнтований:

| Компонент | Джерело даних | Готовність |
|---|---|---|
| `ProductExtractor` | `CatalogProductClient` → catalog-service `/api/products` (пагінація) | ✅ HTTP |
| `OrderExtractor` | `OrderClient` → order-service `list()` | ✅ HTTP |
| `UserExtractor` | **`EntityManager` → БД моноліту** (`User`) | ⚠️ треба на HTTP |
| результат (`ProcessExportHandler`) | `FileStorageInterface` → **storage-service** | ✅ HTTP (щойно) |
| лист про завершення | `Mailer` (прямо) | self-contained |

- `ExportJob` (UUID, `exports`-схема): type/format/status/filePath/filters/requestedBy/timestamps.
- `ExportService::dispatch` — persist job + `ProcessExportMessage` на async-транспорт.
- `RequeueExportJobsCommand` (`app:export:requeue-pending`) — реквеню застряглих pending.
- Admin UI: `ExportController` (index/submit/download) + `templates/admin/export/*`.
- user-service `GET /api/users` (ROLE_ADMIN) → `{id,email,fullName,roles}` для ВСІХ (без фільтрів/пагінації).

## Ключові рішення

| Питання | Рішення |
|---|---|
| База даних | **Власна** (`db-export`, database-per-service) — на відміну від stateless Storage/Notification. Має міграції. |
| Порт | **:8009** (після storage :8008). |
| `UserExtractor` | Переписати на **`UserClient` → user-service `/api/users`** (S2S токен з `ROLE_ADMIN`), фільтрація email/role **локально** (API не фільтрує). |
| Клієнти сервісу | `CatalogProductClient`, `OrderClient`, новий `UserClient`, `StorageClient` — усі S2S JWT (сервіс **підписує**, треба `JWT_PASSPHRASE`+приватний ключ). |
| Тригер експорту | Моноліт-admin → **`ExportClient`** → export-service `POST /api/exports`. Admin UI лишається в моноліті (проксі). |
| Лист про завершення | **Mailer у самому сервісі** (self-contained, як зараз); event→notification — окремо/пізніше. |
| Стара історія job'ів | **Не мігруємо** (транзієнтна історія експортів); монолітні `export_jobs` дропаємо після cutover. |

### Рішення, які треба підтвердити
1. **Завантаження файлу:** (A) моноліт читає файл прямо зі storage-service власним `StorageClient` після
   authz по `requestedBy` — **менше API, natural authz** *(рекоменд.)*; чи (B) export-service віддає
   `GET /api/exports/{id}/download`, моноліт проксіює.
2. **Лист:** Mailer у сервісі *(рекоменд.)* чи публікація `ExportCompleted`→notification-service.

## Контракт сервісу

```
POST /api/exports              → створити+dispatch job {type,format,requestedBy,filters} → {id,status,...}
GET  /api/exports              → останні job'и (для admin index)
GET  /api/exports/{id}         → статус job'а
GET  /api/exports/{id}/download → (лише якщо рішення 1B) стрім результату
GET  /health/live | /health/ready → ready = БД + (опц.) storage-service
worker: consume export_jobs → extract → format → storage.write → mark → email
```

## Кроки

### Крок 1 — Каркас + БД + health
- [x] `services/export-service/` (FrankenPHP, :8009), `db-export`, doctrine+migrations+lexik+security+mailer+messenger+http-client.
- [x] Перенести `ExportJob`+`ExportJobRepository`+enums; міграція `export_jobs` (написана вручну — звірити `schema:validate`); `composer.lock` — ⏳ потребує Docker.
- [x] `/health/live`; `/health/ready` (перевірка БД). Тестовий keypair `config/jwt-test`; `/jwt` mount у dev.

### Крок 2 — API + worker + клієнти + S2S
- [x] `ExportController` (`POST/GET /api/exports` + `GET /api/exports/{id}`) + `ExportService` (persist+dispatch).
- [x] Перенести формати (CSV/JSON/XML) + екстрактори; `UserExtractor` → `UserClient` (user-service, ROLE_ADMIN, локальна фільтрація).
- [x] Перенести `CatalogProductClient`+`OrderClient`+`StorageClient`(write); worker `ProcessExportHandler` (extract→format→storage.write→mark→email inline через `Email::html()`, ідемпотентність).
- [x] `RequeueExportJobsCommand`. `security.yaml`: `^/api/exports`→`ROLE_EXPORT_ADMIN`; health public.
- [x] Тести написані: unit (формати/`UserExtractor` mock/`ExportJob`) + functional (create→201 Pending, 401/403, 404). ⏳ прогін у Docker.
- Примітка: async-черга = Doctrine-транспорт на db-export (self-contained, без RabbitMQ); лист = Mailer у сервісі (без TwigBundle).

### Крок 3 — Cutover моноліту
- [ ] `ExportClient` (S2S) у моноліті; `ExportController`: submit→`create`, index→`listRecent`,
      download→(рішення 1) job+authz+`StorageClient::read`.
- [ ] Видалити з моноліту: `ExportJob`/repo/`ExportService`/`ProcessExportHandler`/`ProcessExportMessage`/
      extractors/formatters/`CatalogProductClient`/`RequeueExportJobsCommand`; messenger-routing; doctrine Export mapping.
- [ ] Дроп `exports`-схеми/таблиць (міграція). Оновити/перенести монолітні Export-тести.

### Крок 4 — Prod overlay + CI
- [ ] `compose.yaml`+`compose.prod.yaml` (сервіс+worker+db, за зразком cart/order); мережі default+monolith.
- [ ] `.gitlab-ci.yml`: build/test/deploy `export-service` (**з міграціями**, за зразком cart/order — не notification).
- [ ] CI-змінні: `EXPORT_DB_PASSWORD` (нова, як `CART_DB_PASSWORD`); URL-и catalog/order/user/storage + JWT — уже є.

## Edge cases (rule 3)
- `UserExtractor` без фільтрів/пагінації в API → тягне всіх, фільтрує локально; великий обсяг → прийнятно (як зараз findAll).
- Extractor-клієнт лежить (catalog/order/user down) → job → Failed з повідомленням (як зараз try/catch у handler).
- storage-service лежить при write → job Failed; retry Messenger.
- Дублікат обробки (at-least-once) → job уже Completed → ідемпотентно пропустити (перевірка статусу).
- Download неіснуючого/чужого job'а → 404/403 (authz по `requestedBy`).

## Тест-кейси (rule 3)
- Health: live=200; ready=200 при доступній БД; 503 коли БД впала.
- `POST /api/exports` → job Pending + повідомлення в черзі; 401 без токена; 403 без `ROLE_EXPORT_ADMIN`.
- Worker: кожен `type` → правильний екстрактор → форматер → `storage.write` виклик → job Completed + лист.
- `UserExtractor` з фільтром email/role → локальна фільтрація повертає підмножину.
- Extractor кидає → job Failed + errorMessage + лист про помилку.
- Моноліт після cutover: admin index/submit/download через `ExportClient` — зелені.

## Огляд результатів
_(заповнюється в міру виконання)_

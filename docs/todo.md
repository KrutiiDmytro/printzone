# Фаза 2 — RabbitMQ + Transactional Outbox (event backbone)

> Адитивна інфраструктура: брокер RabbitMQ + outbox (атомарна публікація доменних подій).
> Демо: OrderPaid round-trip (webhook→outbox→relay→RabbitMQ→email). Гілка `feat/phase2-rabbitmq-outbox`.
> UUID НЕ чіпаємо (відкладено). Тести — `in-memory` транспорт (без брокера).

## Під-задача 1 — Інфраструктура RabbitMQ ✅
- [x] `Dockerfile.php`: `ext-amqp`; `symfony/amqp-messenger` (v7.4.11)
- [x] `compose.yaml`: сервіс `rabbitmq` (3.13-management) + volume; `compose.prod.yaml`: env + ports:[]
- [x] `.env`: `MESSENGER_EVENTS_DSN`; `messenger.yaml`: транспорт `events` (amqp topic)
- [x] `config/packages/test/messenger.yaml`: `async`+`events`→`in-memory`
- [x] Верифікація: rabbitmq healthy; `phpunit` (141) зелений

## Під-задача 2 — Outbox: таблиця + entity + recorder ✅
- [x] `OutboxMessage` entity + `Messaging` мапінг + міграція `outbox` (INT identity, index unpublished)
- [x] `OutboxRecorder` (persist без flush) + `OutboxMessageRepository::findUnpublished` (`@extends`)
- [x] Тести (`OutboxMessageTest`,`OutboxRecorderTest`); `phpunit` (144) + `phpstan` [OK] (baseline 109→110); mapping [OK]

## Під-задача 3 — Relay (outbox → RabbitMQ) ✅
- [x] `IntegrationEvent` DTO + `OutboxRelay` (publish→mark published, at-least-once; AmqpStamp routing key)
- [x] `app:outbox:relay` command (--time-limit loop) + сервіс `relay` у compose/prod; routing `IntegrationEvent→events`
- [x] `OutboxRelayTest`; `phpunit` (146) + `phpstan` [OK]; E2E: relay → exchange `events` у RabbitMQ, рядок published

## Під-задача 4 — Emit OrderPaid + consumer
- [ ] `StripeWebhookController`: на PAID `outboxRecorder->record('order','OrderPaid',...)` (атомарно)
- [ ] `IntegrationEventHandler` → email через Mailpit; worker consume `events`
- [ ] Тести; E2E

---

# Фаза 1 декомпозиції — розв'язати міждоменну зв'язність (моноліт)

> Замінити міждоменні Doctrine FK на скалярні int-посилання + знімки + подію. Один застосунок,
> поведінка незмінна. UUID відкладено на Фазу 2.1. Гілка `feat/phase1-decouple-modules`.
> Кожна під-задача — окремий коміт, між ними `phpunit` + `phpstan` зелені.

## Під-задача 1 — CartItem ⟂ Catalog ✅
- [x] `CartItem`: прибрати `product` ManyToOne → `productId:int` + знімки `productName`,`price`; `getTotal()` зі `price`
- [x] `CartService`: знімки на `add`; зіставлення за `productId`; `getCartFromDatabase()` масово вантажить продукти
- [x] `CartRepository::findOneByUser`: прибрати `leftJoin('i.product')`
- [x] Тести: `CartItemTest`, `CartTest`, `CartServiceTest`, `CheckoutControllerTest`
- [x] Міграція: `cart_items` +`product_name`,`price`, drop FK на `products`
- [x] Верифікація: `phpunit` (141) + `phpstan` зелені — коміт `48ecb81`

## Під-задача 2 — OrderItem ⟂ Catalog ✅
- [x] `OrderItem`: `product` ManyToOne → `productId:int` + `productName` (price вже є); `__toString` через `productName`
- [x] `CheckoutController`: `setProductId/setProductName` зі знімка кошика
- [x] `templates/admin/order/items.html.twig`: `item.productName`
- [x] Міграція + тести (`OrderTest`) — `phpunit` (141) + `phpstan` зелені

## Під-задача 3 — Order/Cart ⟂ User ✅
- [x] **3a** `Cart`: `user` → `userId:int` (unique); `CartRepository::findOneByUserId`; CartService — коміт `05581d3`
- [x] **3b** `Order`: `user` → `userId:int` + `userEmail` (знімок); CheckoutController; `OrderRepository::findByUserId`
- [x] **3b** `OrderExtractor`: прибрано `leftJoin('o.user')` → знімок `o.userEmail`
- [x] **3b** `OrderCrudController`: `AssociationField('user')`/`EntityFilter` → текст на `userEmail`
- [x] Міграції `carts`/`orders` + тести; baseline 111→109 — коміт `fb98664`

## Під-задача 4 — LoginListener → подія UserLoggedIn ✅
- [x] Подія `UserLoggedIn` (`src/User/Domain/Event/`) + диспатч із `LoginListener` (замість прямого CartService)
- [x] Слухач `MigrateGuestCartOnLogin` (`src/Cart/Application/EventListener/`) → міграція кошика
- [x] Тести: `LoginListenerTest` (диспатч) + `MigrateGuestCartOnLoginTest`; `phpunit` (142) + `phpstan` зелені

## Підсумок Фази 1
Усі міждоменні Doctrine FK розв'язані (Cart/Order ⟂ Catalog/User), синхронний виклик при вході →
подія. Моноліт цілий, поведінка незмінна. UUID — Фаза 2.1.

---

# Аудит і виправлення документації мікросервісів (Task 24)

> Звірка наявних `docs/microservices-architecture.md`, `docs/event-catalog.md`, `README.md` з фактичним
> кодом + виправлення розбіжностей. Лише документація — код застосунку не змінювався (3 файли).

## Виправлено
- [x] **H1** Inventory: додано таблицю `stock_reservations` + семантику HELD/COMMITTED/RELEASED; примітка в §3, чому інвентар co-located у Catalog
- [x] **H2** Stripe: Saga (§4.4) і Payment (§4.5) переписані під redirect-модель Checkout Session + webhook (не headless-списання); узгоджено діаграму в event-catalog
- [x] **H3** Статуси Order: enum приведено до коду — `PENDING, PAID, FAILED, PROCESSING, SHIPPED, DELIVERED, CANCELLED` (прибрано `PAYMENT_PENDING`, додано `FAILED`)
- [x] **H4** Міграція PK `serial int → UUID` винесена явним підкроком 2.1 у §10 з позначкою ризику
- [x] **M1** У схему Catalog додано `brands`, `printer_models`, `products.brand_id`
- [x] **M2** У §4.1 — примітка про код-гап: сутність `User` ще не має поля `githubId`
- [x] **M3** Додано підрозділ «Transactional Outbox» (§5) зі схемою таблиці; крос-посилання з §9
- [x] **M4** У §1/§2 і README позначено Delivery+Notification як greenfield, решту — як витягнуті модулі
- [x] **M5** У §4.3 — примітка, що гостьовий кошик сесія→БД є зміною поведінки
- [x] **L1** README: «22 типи подій» → 24 (дві згадки)
- [x] **L2** Вирівняно payload подій: `ProductCreated.categoryId`, `OrderCancelled.userId`, `PaymentRequested.{paymentId,idempotencyKey}`, `Payment*` providerTxId/providerCode
- [x] **L3** Домен виправлено: PrintZone друкарський магазин (не «електроніка»); заголовки узгоджено
- [x] **L4** Circuit Breaker/mTLS — додано конкретику механізму (ganesha / service mesh)

## Верифікація
- [x] `grep PAYMENT_PENDING docs/ README.md` → порожньо
- [x] enum статусів Order == `OrderCrudController.php:69-75` (+ FAILED)
- [x] схема Catalog містить усі 6 сутностей коду
- [x] `grep "22 тип|електроніки"` → порожньо

---

# Гігієна перед мікросервісами (Трек A)

> Підняти якість/відтворюваність коду перед виносом у мікросервіси. Без зміни звʼязності
> (Phase 1 не чіпаємо). Усі зміни поведінково нейтральні. Гілка `chore/hygiene-microservices-prep`.

## Задача 1 — Закріпити версії залежностей
- [x] `composer.json`: `stripe/stripe-php` `*`→`^20.2`, `doctrine/doctrine-fixtures-bundle` `*`→`^4.3.1`
- [x] `composer update` лише цих 2 пакетів (stripe v20.1→v20.2), `composer validate` ок

## Задача 2 — php-cs-fixer (єдиний стиль)
- [x] `composer require --dev friendsofphp/php-cs-fixer` (^3.95)
- [x] `.php-cs-fixer.dist.php` — Finder src+tests, `@Symfony` + `@PHP82Migration` (без risky)
- [x] `php-cs-fixer fix` один раз → 88 файлів переформатовано (окремий style-коміт)
- [x] `.gitlab-ci.yml` — job `cs:fixer` у validate (image ghcr.io/php-cs-fixer, `check`)

## Задача 3 — PHPStan + symfony extension
- [x] `composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/extension-installer`
- [x] `phpstan.dist.neon` — level 6, paths src+tests, containerXmlPath, includes baseline
- [x] згенерувати `phpstan-baseline.neon` (111 помилок у baseline)
- [x] `.gitlab-ci.yml` — job `static:analysis` у validate (composer:2: install→warmup→analyse)

## Верифікація
- [x] `php-cs-fixer check` → 0 порушень
- [x] `phpstan analyse` → [OK] No errors (з baseline)
- [x] `phpunit` → OK (141 tests, 348 assertions) — поведінка не змінилась

## Review

### Що зроблено
1. **Версії**: `stripe/stripe-php *`→`^20.2`, `doctrine-fixtures-bundle *`→`^4.3.1` (відтворювані білди).
2. **php-cs-fixer** ^3.95: `@Symfony` + `@PHP82Migration` (non-risky), 88/111 файлів переформатовано.
3. **PHPStan** 2.2 + phpstan-symfony: level 6, baseline 111 помилок (CI зелений; борг знижуємо «храповиком»).
4. **CI**: дві нові job-и у стадії `validate` — `cs:fixer` (check) і `static:analysis` (phpstan).

### Структура комітів
- `style:` — лише переформатування `src/`+`tests/` (механічний diff, ізольований для рев'ю).
- `chore(quality):` — тулінг, конфіги, baseline, CI, закріплення версій (composer.json/lock
  переплетені між задачами → не діляться по файлах, тому згруповані).

### Поза обсягом (свідомо, не гігієна)
Ідемпотентність webhook, Phase 1 розв'язання FK, розбиття CartService, зняття типів з baseline.

### Як знижувати PHPStan-борг далі
`phpstan-baseline.neon` (111) — додавати типи generics (Doctrine Collections), `TEntity`
в EasyAdmin CRUD, прибирати застарілі `int|null` на `$id`. Видаляти записи з baseline по мірі фіксу.

---

# Price Range Filter (euros)

## Checklist
- [x] 1. `ProductRepository` — `findWithFilters()` + `getPriceRange()`
- [x] 2. `ShopController` — всі 4 маршрути читають `price_min`/`price_max`, передають межі слайдера
- [x] 3. `templates/shop/index.html.twig` — форма фільтрації, `$`→`€`, active tag

## Files
1. `src/Repository/ProductRepository.php`
2. `src/Controller/ShopController.php`
3. `templates/shop/index.html.twig`

---

# PrinterModel + Printer Finder Filter

## Підзадачі
- [x] Підзадача 1: `PrinterModel` entity + `PrinterModelRepository` (2 файли)
- [x] Підзадача 2: Doctrine migration + fixtures з моделями для 8 брендів
- [x] Підзадача 3: `BrandExtension` — додати `brand_models(slug)` + `PrinterModelCrudController` (2 файли)
- [x] Підзадача 4: `home.html.twig` — блок "Printer Finder" з autocomplete + cascading dropdowns

## Архітектура
```
Brand (1) ──► PrinterModel (many)
              id, name, slug, brand_id

Twig: brand_models('canon') → PrinterModel[]
JS autocomplete: text input → filter all models → show dropdown
Cascading: select brand → load models for that brand
Search redirect: /brand/{slug}
```

---

# Brand Entity — повноцінна реалізація

## Підзадачі
- [x] Підзадача 1: Brand entity (`src/Catalog/Domain/Entity/Brand.php`) + BrandRepository + міграція
- [x] Підзадача 2: `brand` поле в Product entity + `findByBrand()` в ProductRepository + міграція
- [x] Підзадача 3: `ShopController::showBrand()` маршрут + `BrandTwigExtension` (`all_brands()`)
- [x] Підзадача 4: `BrandCrudController` + пункт "Бренди" в `DashboardController`
- [x] Підзадача 5: Fixtures — 8 брендів з кольорами, прив'язка продуктів
- [x] Підзадача 6: Шаблони — видалено хардкод у `base.html.twig` та `home.html.twig`

## Результат
- Бренди зберігаються в БД (таблиця `brands`: name, slug, color)
- Кожен продукт має `brand_id` (nullable FK)
- Маршрут `/brand/{slug}` повертає продукти фільтровані за брендом
- `all_brands()` доступна у всіх Twig-шаблонах глобально
- Адмінка має CRUD для брендів

---

# PrintZone — Redesign шапки сайту

## План
- [x] Записати план
- [x] base.html.twig — назва "PrintZone", іконка принтера, нова категорійна navbar з dropdown-брендами
- [x] home.html.twig — hero-заголовок і підзаголовок під PrintZone

---

# Task 25 — CI/CD & S3 Storage

## CI/CD Status
- [x] Fixed PDOException: added pdo_sqlite driver for test env
- [x] Fixed memory_limit: raised to 256M in phpunit config
- [x] Fixed build:image: use CI_JOB_TOKEN for GitLab registry auth
- [x] Added SSH deployment with SSH_PRIVATE_KEY
- [x] Added AWS S3 variables to GitLab CI

---

# Task 23 — Data Export Module

## Plan

- [x] Sub-task 1: Enums (ExportStatus, ExportType, ExportFormat), ExportJob entity, ExportJobRepository, Doctrine migration
- [x] Sub-task 2: ExportFormatterInterface, CsvFormatter, JsonFormatter, XmlFormatter, ExportExtractorInterface, ProductExtractor, OrderExtractor, UserExtractor, ProcessExportMessage, ProcessExportHandler, ExportService
- [x] Sub-task 3: ExportController (GET/POST/download), templates (index.html.twig, email.html.twig)
- [x] Sub-task 4: messenger.yaml routing, DashboardController menu item
- [x] Unit tests: CsvFormatterTest, JsonFormatterTest, XmlFormatterTest (9 tests, all pass)
- [x] Sub-task 5: CSRF захист форми (template + controller validation)
- [x] Sub-task 6: Unit tests (ExportJobTest, ExportServiceTest, ProcessExportHandlerTest) + розширення CsvFormatterTest
- [x] Sub-task 7: Functional tests (ExportControllerTest — 8 тестів access control + POST + download)

## Architecture

```
Admin UI (/admin/export)
    │  POST form (type + format + filters)
    ▼
ExportController → ExportService → ExportJob (DB, status: pending)
                                         │
                                         ▼ Messenger async
                               ProcessExportHandler
                                    │       │
                              Extractor  Formatter
                                    │       │
                                    └──► S3 (FileStorageInterface::write)
                                         │
                                  ExportJob (status: completed, file_path)
                                         │
                                  Email notification → Admin
```

## Review

### Що зроблено
1. **Domain**: PHP 8.1 backed enums (ExportStatus/Type/Format), `ExportJob` entity з полями type/format/status/filePath/filters/createdAt/completedAt/errorMessage/requestedBy
2. **Formatters**: CSV (fputcsv), JSON (json_encode), XML (SimpleXMLElement + DOMDocument)
3. **Extractors**: Product (фільтри: category/isFeatured/priceMin/priceMax/stockMin/stockMax), Order (status/dateFrom/dateTo), User (email/role)
4. **Background processing**: `ProcessExportMessage` → async transport → `ProcessExportHandler` (#[AsMessageHandler])
5. **S3 storage**: файли зберігаються за шляхом `exports/{type}/{format}/{id}-{timestamp}.{ext}`
6. **Email**: `TemplatedEmail` через `admin/export/email.html.twig`
7. **Admin UI**: кастомна EasyAdmin-сторінка з Bootstrap-формою (динамічні фільтри JS), таблицею завдань, кнопкою download
8. **Wiring**: messenger.yaml routing, `ADMIN_EMAIL` env var, `services.yaml` service config, doctrine.yaml Export mapping, DashboardController menu

### Edge cases
- Порожній набір даних → порожній файл (коректно для CSV/JSON/XML)
- Невалідний тип/формат → flash error, redirect
- Помилка в handler → ExportJob.status = failed, email з текстом помилки
- Retry у Messenger: max_retries=3, multiplier=2 (вже налаштовано)

### Запуск worker
```bash
docker compose exec php php bin/console messenger:consume async --limit=10
```

---

# Task 22 — Master-Slave (Primary-Replica) Replication

## Plan

- [x] docker/postgres/primary/pg_hba.conf — дозволити replication-з'єднання
- [x] docker/postgres/primary/init/01_replication_user.sql — створити користувача replicator
- [x] docker/postgres/replica/entrypoint.sh — pg_basebackup + запуск standby
- [x] compose.yaml — WAL params на primary + новий сервіс database-replica
- [x] .env — додати DATABASE_REPLICA_URL
- [x] config/packages/doctrine.yaml — додати replicas: конфіг

## Architecture

```
[Symfony App]
     │
     ├─ writes ──► [PostgreSQL Primary :5432]
     │                       │
     └─ reads ───► [PostgreSQL Replica :5432] ◄── WAL streaming
```

Doctrine DBAL `PrimaryReadReplicaConnection`:
- SELECT → replica
- INSERT / UPDATE / DELETE / транзакції → primary

## Notes
- Replica ініціалізується через `pg_basebackup -R` (автоматично створює standby.signal)
- `hot_standby=on` дозволяє SELECT-запити на репліці
- При недоступній репліці Doctrine кидає виняток — для production потрібен proxy (PgBouncer)

## Review

### Що зроблено
1. **Primary**: увімкнено WAL streaming (`wal_level=replica`, `max_wal_senders=3`, `max_replication_slots=3`), змонтовано кастомний `pg_hba.conf` та init-SQL для user `replicator`.
2. **Replica**: кастомний entrypoint-скрипт — чекає на primary → `pg_basebackup -R` (автоматично `standby.signal` + `primary_conninfo`) → старт у `hot_standby=on`.
3. **Doctrine**: `driver: pdo_pgsql` + `replicas: replica1: url:` — DBAL використовує `PrimaryReadReplicaConnection`: SELECT → replica, write/transactions → primary.
4. **Перевірено**: `doctrine:schema:validate` повертає [OK] для обох (mapping + database); `pg_stat_replication` показує `streaming / async`.

### Підводний камінь (вирішено)
Doctrine-bundle встановлює `driver: pdo_mysql` як дефолт. Без явного `driver: pdo_pgsql` у `doctrine.yaml` replica-з'єднання падало з `could not find driver` — бо `pdo_mysql` не встановлений у PHP-контейнері. Також для репліки потрібна повна `url:` (а не лише `host:`) — бо doctrine-bundle не наслідує user/password з primary URL у replica params.

### Edge cases
- Якщо `database-replica` недоступна — Doctrine кине `DBAL\Exception` при першому SELECT.
- `start_period: 90s` у healthcheck репліки враховує час `pg_basebackup`.
- `depends_on: database: condition: service_healthy` — replica стартує тільки після healthy primary.
- При зміні `pg_hba.conf` потрібно: `docker compose down -v && docker compose up`.

### Обмеження (для production)
- Немає автоматичного failover — при падінні primary потрібен ручний switchover.
- Для автофailover: Patroni або PgBouncer перед Doctrine.
- Реплікація async — можлива мінімальна втрата даних при failover.

---

# Stripe Checkout Integration (EUR)

## Архітектура флоу

```
[Checkout form] → POST /checkout/pay
    │
    ▼
CheckoutController::pay()
    │  створює Order (status: PENDING)
    │  зберігає stripe_session_id в Order
    ▼
StripeCheckoutService::createSession(Order)
    │  line_items з cart (ціни вже в центах)
    │  success_url: /checkout/success?session_id={CHECKOUT_SESSION_ID}
    │  cancel_url: /checkout/cancel
    ▼
redirect → Stripe hosted page
    │
    ├─ [Успіх] → GET /checkout/success → показує сторінку подяки
    │
    └─ [Webhook] POST /stripe/webhook
           │  перевіряє Stripe-Signature
           │  checkout.session.completed event
           ▼
       Order.status = PAID (надійне підтвердження)
```

## Підзадачі

### Фаза 1 — Setup (≤3 файли)
- [x] 1.1 `composer require stripe/stripe-php`
- [x] 1.2 `.env` — додати `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`
- [x] 1.3 `src/Order/Domain/Entity/Order.php` — додати поле `stripeSessionId` (nullable string)
- [x] 1.4 Doctrine migration для нового поля

### Фаза 2 — Service + Controller (≤3 файли)
- [x] 2.1 `src/Payment/Service/StripeCheckoutService.php` — новий сервіс, створює Stripe Checkout Session
- [x] 2.2 `src/Controller/CheckoutController.php` — переробити `placeOrder` → `pay()`, редірект на Stripe

### Фаза 3 — Webhook + Security (≤2 файли)
- [x] 3.1 `src/Controller/StripeWebhookController.php` — POST `/stripe/webhook`, верифікація підпису, Order→PAID
- [x] 3.2 `config/packages/security.yaml` — вивести `/stripe/webhook` з-під CSRF

### Фаза 4 — Шаблони (≤2 файли)
- [x] 4.1 `templates/checkout/success.html.twig` — сторінка успішної оплати
- [x] 4.2 `templates/checkout/cancel.html.twig` — сторінка скасування

## Ключові деталі реалізації

| Деталь | Рішення |
|--------|---------|
| Ціни | `price` в БД — вже в центах (int). Stripe теж приймає центи → конвертація не потрібна |
| Валюта | `eur` |
| `stripeSessionId` | зберігається в Order до редіректу; webhook шукає Order по цьому полю |
| Webhook безпека | `Stripe::constructEvent()` з `STRIPE_WEBHOOK_SECRET` верифікує підпис |
| CSRF | Webhook endpoint виключений (`stateless: true` або `security: false`) |
| Локальне тестування | `stripe listen --forward-to host.docker.internal/stripe/webhook` |

## Тестові картки Stripe
- `4242 4242 4242 4242` — успішна оплата
- `4000 0000 0000 9995` — declined

## Edge cases
- Порожній кошик → redirect на `/cart` (вже є)
- Stripe session expired → повторний checkout (cancel_url поверне на `/cart`)
- Webhook fires після redirect success — порядок не гарантований, тому статус PAID ставиться лише вебхуком
- Дублювання вебхуків — ідемпотентна обробка (if Order.status !== PAID → set PAID)

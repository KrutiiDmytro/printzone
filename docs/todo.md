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

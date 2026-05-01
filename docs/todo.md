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

# Task 23 — Master-Slave (Primary-Replica) Replication

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

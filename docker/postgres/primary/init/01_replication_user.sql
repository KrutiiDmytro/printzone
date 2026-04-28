-- Створюємо користувача для streaming replication (ідемпотентно)
DO $body$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = 'replicator') THEN
    CREATE USER replicator WITH REPLICATION LOGIN PASSWORD 'replicator_pass';
  END IF;
END
$body$;

-- Створюємо користувача для streaming replication
-- REPLICATION — мінімальні привілеї, необхідні для pg_basebackup та WAL streaming
CREATE USER replicator WITH REPLICATION LOGIN PASSWORD 'replicator_pass';

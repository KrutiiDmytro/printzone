#!/bin/sh
set -e

PRIMARY_HOST="${PRIMARY_HOST:-database}"
PRIMARY_PORT="${PRIMARY_PORT:-5432}"
REPLICATION_USER="${REPLICATION_USER:-replicator}"
PGDATA="${PGDATA:-/var/lib/postgresql/data}"

echo "[replica] Чекаємо на primary ${PRIMARY_HOST}:${PRIMARY_PORT}..."
until pg_isready -h "${PRIMARY_HOST}" -p "${PRIMARY_PORT}" -q; do
    sleep 2
done
echo "[replica] Primary готовий."

if [ ! -f "${PGDATA}/PG_VERSION" ]; then
    echo "[replica] Запускаємо pg_basebackup..."
    mkdir -p "${PGDATA}"
    chmod 700 "${PGDATA}"
    chown postgres:postgres "${PGDATA}"

    # -R автоматично створює standby.signal і primary_conninfo в postgresql.auto.conf
    gosu postgres pg_basebackup \
        -h "${PRIMARY_HOST}" \
        -p "${PRIMARY_PORT}" \
        -U "${REPLICATION_USER}" \
        -D "${PGDATA}" \
        -Fp \
        -Xs \
        -R \
        -P \
        --checkpoint=fast

    echo "[replica] pg_basebackup завершено."
fi

echo "[replica] Запускаємо PostgreSQL у режимі hot standby..."
exec /usr/local/bin/docker-entrypoint.sh postgres \
    -c hot_standby=on \
    -c hba_file=/etc/postgresql/pg_hba.conf

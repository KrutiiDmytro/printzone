# Прод-інцидент 2026-08-05: усунення конфлікту Task-28 ↔ прод

> Активний робочий план. Попередні плани (OpenAPI-специфікації, Export — Phase 7,
> Storage — MR !32, Notification та ін.) — у git-історії.

## Мета

Прибрати конфлікт між CI Task-28 і продом printzone на спільному дроплеті, повернути
асинхронну гілку в робочий стан і задокументувати розбір так, щоб він не вводив в оману.

## Чекліст

- [x] RabbitMQ піднято, swap 2 ГБ, `restart: unless-stopped` для 6 контейнерів ядра сайту
- [x] Пайплайн Task-28 заморожено (`workflow.rules → when: never`), проєкт заархівовано
- [x] `printzone`: `develop` → `main` (MR !38), remote переведено на нову адресу
- [x] Розбір записано в `docs/ops-2026-08-05-incident-and-handoff.md`
- [x] Перевірити merged-конфіг усіх 9 сервісів (`docker compose config`) — мережі воркерів
- [x] Перевірити на дроплеті `docker ps --filter "status=restarting"` — чи ожив delivery-worker
- [x] Полагодити delivery-worker: `stop` → `network connect task-25_default` → `start`
- [x] Переписати розділ 3 доку за фактами (хибний висновок про overlay-файли)
- [x] Записати урок у `docs/lesson.md`
- [ ] Закомітити док окремою гілкою `docs/ops-incident-2026-08-05` + MR у `develop`

## Review (результати)

**Головне: діагноз першої редакції доку був хибний.** Твердження «7 із 8 воркерів не мають
мережі в `compose.prod.yaml` → відкладена міна» побудоване на читанні лише overlay-файлів.
`docker compose -f compose.yaml -f compose.prod.yaml config` (перевірено локально для всіх
9 сервісів і на дроплеті для delivery) дає **всім 8 воркерам/релеям `default + monolith`** —
блоки `networks` лежать у базових `compose.yaml`. Правка overlay була б no-op.

Реальна причина — дрейф стану контейнера: три delivery-контейнери створені однією командою
о `2026-07-25T11:43:40`, двом мережа `task-25_default` дісталася, воркеру ні; ID мережі
незмінний із 2026-05-17. Найімовірніше — гонка з краш-лупом воркера при створенні.

**Фікс і верифікація (2026-08-05):** `docker stop` → `docker network connect` → `docker start`
(зберігає env контейнера; `--force-recreate` руками заборонений — обнуляє `${CI_VAR}`).
Після цього: воркер `running`, `RestartCount=0`, обидві мережі, у логах
`[OK] Consuming messages from transport "delivery_events"`; `docker ps --filter status=restarting`
порожній; жодного контейнера без `unless-stopped`; черга `delivery_shipment_events` існує,
0 повідомлень; сайт `HTTP/2 200`; swap 2 ГБ, вільно ~700 МБ.

**Не увійшло (свідомо, окремими задачами):**
- `symfony/amazon-mailer` для notification-service — зміни лежать незакоміченими в робочому
  дереві; без бриджа прод-`MAILER_DSN=ses+api://` ламає воркер сповіщень.
- Незмерджена гілка `chore/api-platform-config-reference`.
- 49 системних оновлень на дроплеті (13 безпекових).

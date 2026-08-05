# Прод-інцидент 2026-08-05 і стан справ

Розбір падіння асинхронної гілки на проді, перелік змін і **відкриті питання**.
Документ писався як передача контексту: усе, що нижче, або перевірено командою,
або явно позначено як припущення.

- **Хост:** `68.183.67.77` (`ubuntu-s-1vcpu-1gb-fra1-01`), 1 vCPU / 1.9 ГБ, Ubuntu 24.04
- **Сайт:** https://e-commerce.it.com, тека застосунку `/var/www/app`
- **Репозиторій:** перейменовано `foxmidedteam/task-25` → **`foxmidedteam/printzone`**

---

## 1. Що сталося

Ланцюжок, від наслідку до кореня:

1. Три воркери (`app-worker-1`, `catalog-worker`, `notification-worker`) два дні
   перезапускались із `Could not connect to the AMQP server`.
2. Причина — `app-rabbitmq-1` був `Exited (137)`. Код 137 = SIGKILL від ядра,
   тобто **OOM-kill**.
3. Пам'ять закінчилась, бо на тому ж дроплеті стоїть GitLab-раннер #303, спільний
   із Task-28. Його стадія `integration` піднімала **15 контейнерів** поруч із живим
   магазином. Дати збігаються: джоба ганялася 2026-08-03, RabbitMQ помер тоді ж.
4. Swap на машині був відсутній (`Swap: 0B`), тому ядро не мало варіантів, окрім
   як застрелити процес.
5. RabbitMQ не піднявся сам, бо мав політику рестарту `no`.

Тобто одна помилка інфраструктури (CI на прод-хості) вилилась у двохденний простій
асинхронної гілки, і ніщо не сигналізувало про це.

---

## 2. Що змінено

### На хості

| Дія | Команда | Стан |
|---|---|---|
| Піднято RabbitMQ | `docker start app-rabbitmq-1` | ✅ `healthy` |
| Додано 2 ГБ swap | `fallocate` + `mkswap` + `swapon`, рядок у `/etc/fstab` | ✅ переживає ребут |
| Політика рестарту RabbitMQ | `docker update --restart unless-stopped app-rabbitmq-1` | ✅ |
| Політика рестарту ядра сайту | те саме для `app-nginx-1`, `app-php-1`, `app-database-1`, `app-database-replica-1`, `app-certbot-1`, `app-mailer-1` | ✅ |
| Прибрано мертві контейнери | `docker rm` шести `task-25-*` (Exited по 2 місяці) | ✅ |
| Перезавантаження | `reboot` | ✅ |

> **Важливо:** до правки політик усі шість контейнерів ядра сайту мали `restart: no`.
> Ребут поклав би магазин, і він не піднявся б без ручного втручання.

### У репозиторіях

- `printzone`: `develop` → `main` (MR !38). До того `main` стояв на травні й показував
  моноліт **без** каталогу `services/`. Тепер `main` = `develop`, відставання 0.
- Локальний remote переведено на нову адресу, застарілий remote `task-24` видалено.
- **Task-28** (SOA, окремий проєкт): пайплайн заморожено (`workflow.rules` → `when: never`),
  проєкт заархівовано в GitLab. Ресурсів дроплета більше не споживає.

### Стан після ребуту (перевірено)

```
Swap:          2.0Gi   780Ki used
docker ps -q | wc -l   →  33
curl -sI https://e-commerce.it.com  →  HTTP/2 200
```

---

## 3. Мережа delivery-worker — ЗАКРИТО 2026-08-05

Після ребуту `delivery-service-delivery-worker-1` не піднявся: він єдиний з трьох
контейнерів delivery не був у мережі, де живе RabbitMQ, тому `rabbitmq` для нього
не резолвився (`Socket error: could not connect to host, hostname lookup failed`).

```
delivery-worker    → delivery-service_default                      ← 48 рестартів
delivery-relay     → delivery-service_default + task-25_default    ← працює
delivery-service   → delivery-service_default + task-25_default    ← працює
app-rabbitmq-1     → task-25_default
```

### Причина — дрейф контейнера, а НЕ конфіг

Первісна версія цього документа стверджувала, що 7 із 8 воркерів не мають мережі в
`compose.prod.yaml`, і це «відкладена міна». **Висновок був хибний: читали лише
overlay.** Блоки `networks: [default, monolith]` для воркерів і релеїв живуть у
базових `compose.yaml`, а деплой завжди запускає обидва файли
(`docker compose -f compose.yaml -f compose.prod.yaml`), тож overlay їх успадковує.

Перевірено `docker compose config` (merged-вивід) для всіх 9 сервісів — і локально,
і на дроплеті в `/var/www/app/services/delivery-service`:

| Контейнер | у `compose.prod.yaml` | у merged-конфігу |
|---|---|---|
| `catalog-worker` | — | `default + monolith` ✅ |
| `delivery-worker` | — | `default + monolith` ✅ |
| `delivery-relay` | — | `default + monolith` ✅ |
| `export-worker` | явно | `default + monolith` ✅ |
| `notification-worker` | — | `default + monolith` ✅ |
| `order-worker` | — | `default + monolith` ✅ |
| `order-relay` | — | `default + monolith` ✅ |
| `payment-relay` | — | `default + monolith` ✅ |

Тобто **правити overlay-файли не потрібно — це був би no-op.** Додаткові факти, які
спростовують «конфіг зламаний»: усі три delivery-контейнери створені однією командою
о `2026-07-25T11:43:40`, двом мережі дісталися, воркеру ні; мережа `task-25_default`
має незмінний ID із 2026-05-17, тобто її ніхто не перестворював. Найімовірніший
механізм — гонка: воркер одразу пішов у краш-луп, і крок приєднання другої мережі
після створення контейнера не доїхав.

### Що зроблено

Ручний `docker network connect` із першої редакції доку ефекту не дав: до контейнера
в стані `restarting` приєднати мережу не можна. Спрацювало через зупинку (env
контейнера зберігається — жодної переінтерполяції `${CI_VAR}`):

```bash
docker stop    delivery-service-delivery-worker-1
docker network connect task-25_default delivery-service-delivery-worker-1
docker start   delivery-service-delivery-worker-1
```

Результат перевірено 2026-08-05:

```
delivery-worker  → running, RestartCount=0, обидві мережі
logs             → [OK] Consuming messages from transport "delivery_events"
docker ps --filter "status=restarting"                       → 0
черга delivery_shipment_events                               → існує, 0 повідомлень
docker inspect … RestartPolicy | grep -v unless-stopped      → порожньо
curl -sI https://e-commerce.it.com                           → HTTP/2 200
```

> `consumers 0` у `rabbitmqctl list_queues` — норма для Symfony AMQP: транспорт
> тягне повідомлення через `basic_get`-полінг, постійного консюмера не тримає.

Приєднання руками живе, доки контейнер не перестворять; наступний
`deploy:delivery-service` створить його з обома мережами за merged-конфігом.
Якщо дрейф повториться — це вже привід підозрювати гонку в compose, а не файли.

---

## 4. Решта відкритих дрібниць

- У робочому дереві висять незакомічені зміни: `services/notification-service/composer.json`
  і `composer.lock` — це доданий `symfony/amazon-mailer`. Без цього бриджа прод-значення
  `MAILER_DSN=ses+api://…` для notification-worker = «unsupported scheme», тобто листи
  з чеками покупцям не йдуть. Винести окремою гілкою + MR (в `export-service` бридж уже є).
- Гілка `chore/api-platform-config-reference` (1 коміт, `config/reference.php`) запушена,
  але не змерджена в `develop`.
- Дроплет тісний: після ребуту вільно ~700 МБ на 33 контейнери (з них 9 Postgres).
  Swap рятує від раптових смертей, але під нові сервіси машину варто розширити.
- 49 системних оновлень, з них 13 безпекових (`apt list --upgradable`).

## 5. Чого НЕ варто робити

- **`docker volume prune`** не глядячи — під ним лежать бази сервісів.
- Піднімати важкий CI на цьому ж хості. Саме це й спричинило інцидент; для Task-28
  проблему закрито заморозкою, але правило загальне.

---

## 6. Корисні команди

```bash
ssh -i ~/.ssh/id_ed25519 root@68.183.67.77

docker ps --filter "status=restarting"        # чи все живе
free -h                                        # пам'ять і swap
docker logs --tail 20 <container>

# які контейнери не піднімуться після ребуту
docker inspect -f '{{.Name}} {{.HostConfig.RestartPolicy.Name}}' $(docker ps -aq) | grep -v unless-stopped

# у яких мережах контейнер
docker inspect -f '{{.Name}}: {{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' <container>
```

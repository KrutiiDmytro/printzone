# Прод: інцидент 2026-08-05 і вікно обслуговування 2026-08-10

Розбір падіння асинхронної гілки на проді, перелік змін і поточний стан.
Документ писався як передача контексту: усе, що нижче, або перевірено командою,
або явно позначено як припущення.

> ⚠️ **Історичний документ — стан на серпень 2026.** Опис CI відповідає тому
> часу: тоді збіркою й деплоєм керував GitLab CI, а посилання `MR !NN` ведуть у
> закриту інсталяцію Foxminded. **З 2026-08-14 деплоєм володіє GitHub Actions**,
> а не GitLab — див. `.github/workflows/ci.yml` (self-hosted раннер на тому ж
> дроплеті). Деталі й причини міграції — у `docs/todo.md`.
> Технічні висновки нижче (мережі, пам'ять, політики рестарту, SES) лишаються
> чинними; змінився лише той, хто натискає кнопку.

- **Хост:** `68.183.67.77` (`ubuntu-s-1vcpu-1gb-fra1-01`), 1 vCPU / 1.9 ГБ, Ubuntu 24.04
- **Сайт:** https://e-commerce.it.com, тека застосунку `/var/www/app`
- **Репозиторій:** перейменовано `foxmidedteam/task-25` → **`foxmidedteam/printzone`**
- **Стан на 2026-08-10:** відкритих питань немає; 33 контейнери живі, 0 безпекових
  оновлень, ядро `6.8.0-137`. Єдина зовнішня залежність — SES production access (розділ 5).

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

### Підтверджено повним деплоєм 2026-08-06

Мерж у `develop` (пайплайн #21931, 35 джоб) перестворив контейнери сервісів — тобто
перевірив рівно те, що перша редакція доку називала міною. Результат: **усі 8 воркерів
і релеїв піднялися з обома мережами, `RestartCount=0`, без жодного ручного втручання.**

```
catalog-worker       running restarts=0  catalog-service_default      task-25_default
order-worker         running restarts=0  order-service_default        task-25_default
order-relay          running restarts=0  order-service_default        task-25_default
notification-worker  running restarts=0  notification-service_default task-25_default
payment-relay        running restarts=0  payment-service_default      task-25_default
export-worker        running restarts=0  export-service_default       task-25_default
delivery-worker      running restarts=0  delivery-service_default     task-25_default   ← created 18:51:49
delivery-relay       running restarts=0  delivery-service_default     task-25_default
```

`delivery-worker` створено заново під час деплою — ручне приєднання мережі скасувалося,
і воркер усе одно отримав `task-25_default` з конфігу. Питання закрите: правити
overlay-файли не треба, поломки в конфігах немає. Якщо дрейф колись повториться —
підозрювати гонку при створенні контейнера, а не файли.

---

## 4. Вікно обслуговування 2026-08-10

Закриття хвостів з розділу 5 попередньої редакції: системні оновлення. Порядок кроків
не косметичний — крок 1 був передумовою решти.

### Крок 1 — політики рестарту (MR !51), БЕЗ нього ребут клав би магазин

Розділ 2 фіксує, що політики виставили руками через `docker update`. За п'ять днів
деплої перестворили частину контейнерів, і **`app-nginx-1` та `app-php-1` знову мали
`restart: no`** — тобто рівно ті два, від яких залежить сайт.

Причина системна: у `compose.yaml` моноліта `restart: unless-stopped` оголошений лише
для `worker` і `relay`. Ручне значення живе до першого перестворення контейнера, а
деплой перестворює саме php і nginx. Решта ядра (`database`, `rabbitmq`, `mailer`,
`certbot`) ще тримала ручне значення й злетіла б так само.

Полагоджено декларативно — `restart: unless-stopped` у `compose.prod.yaml` для всіх семи
сервісів. Саме в prod-overlay, а не в базовий файл: так зроблено в усіх дев'яти винесених
сервісах, і локальний dev-стек не починає стартувати разом із Docker Desktop. Перевірка —
`docker compose config`: prod-мерж дає `unless-stopped` усім дев'яти, dev-мерж і далі лише
`worker`/`relay`.

> **Урок:** будь-яка правка через `docker update` — тимчасова. Якщо політика має пережити
> деплой, вона мусить бути у compose-файлі.

### Крок 2 — системні пакети без docker

Docker-пакети тимчасово під `apt-mark hold`, щоб апгрейд не перезапустив демон посеред
роботи; `NEEDRESTART_MODE=l`, щоб apt не перезапускав служби на льоту; конфіги збережено
через `--force-confdef --force-confold`.

Оновлено **38 пакетів, усі 11 безпекових (systemd) закрито**. `needrestart` підтвердив
«No containers need to be restarted», сайт не падав.

### Крок 3 — ребут: перевірка кроку 1

`unattended-upgrades` уже поставив ядро `6.8.0-137`, тоді як працювало `6.8.0-136`,
тож ребут був потрібен незалежно від нас.

```
до:  6.8.0-136-generic, uptime 5 днів
після: 6.8.0-137-generic, SSH повернувся за ~10 с
магазин піднявся САМ: 33/33 контейнери, diff зі знімком до оновлення порожній
```

П'ять воркерів побули в `restarting` ~2 хв (чекали на свої БД і брокер) і осіли самі.

### Крок 4 — docker + containerd

```
Docker      29.3.0 → 29.7.2
containerd   2.2.1 → 2.3.3
compose      5.1.0 → 5.4.0   (+ buildx, model-plugin)
```

Рестарт демона підняв усі 33 контейнери за політиками — та сама поведінка, що на ребуті.
`apt-mark showhold` порожній: hold знято, docker не лишився замороженим.

### Підсумковий стан

```
33 running / 0 restarting          diff зі знімком до оновлення порожній
політики                            усі unless-stopped
черги RabbitMQ                      6 шт., 0 повідомлень (нічого не втрачено)
mailer                              SesApiAsyncAwsTransport
мережі воркерів                     delivery/order/catalog — обидві
https://e-commerce.it.com           HTTP/2 200
ядро                                6.8.0-137-generic
диск                                80% → 66%
залишилось оновити                  тільки fwupd
```

`fwupd` тримається через зміну залежностей — потребує `apt full-upgrade`. Це оновлювач
прошивок, на віртуалці марний і не безпековий; свідомо не чіпали, бо `full-upgrade` на
проді може доставляти й видаляти пакети.

---

## 5. Решта відкритих дрібниць

- **SES sandbox.** Технічний блокер знято (MR !50 додав `symfony/amazon-mailer`; без
  бриджа `MAILER_DSN=ses+api://…` = «unsupported scheme» і чеки не відправлялись), але
  доки SES у sandbox, листи йдуть лише на верифіковані адреси. Перевірка — замовлення на
  `krutiidmytro@gmail.com` + `docker logs notification-service-notification-worker-1`.
  Для реальних покупців потрібен запит **SES production access**.
- Дроплет тісний: ~650 МБ вільно на 33 контейнери (з них 9 Postgres). Swap рятує від
  раптових смертей, але під нові сервіси машину варто розширити.

## 6. Чого НЕ варто робити

- **`docker volume prune`** не глядячи — під ним лежать бази сервісів.
- Піднімати важкий CI на цьому ж хості. Саме це й спричинило інцидент; для Task-28
  проблему закрито заморозкою, але правило загальне.
- **Закріплювати політики рестарту через `docker update`** — переживає рестарт, але не
  переживає деплой. Тільки compose-файл.
- Оновлювати docker, не переконавшись, що всі контейнери мають `unless-stopped`: рестарт
  демона зупиняє їх усі, і назад підніметься лише те, що має політику.

---

## 7. Корисні команди

```bash
ssh -i ~/.ssh/id_ed25519 root@68.183.67.77

docker ps --filter "status=restarting"        # чи все живе
free -h                                        # пам'ять і swap
docker logs --tail 20 <container>

# які контейнери не піднімуться після ребуту — має бути порожньо ПЕРЕД ребутом
docker inspect -f '{{.Name}} {{.HostConfig.RestartPolicy.Name}}' $(docker ps -aq) | grep -v unless-stopped

# у яких мережах контейнер
docker inspect -f '{{.Name}}: {{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' <container>

# знімок перед оновленням, щоб потім звірити склад
docker ps --format '{{.Names}}' | sort > /root/containers-before-upgrade.txt
diff /root/containers-before-upgrade.txt <(docker ps --format '{{.Names}}' | sort)

# оновлення системи без рестарту docker-демона
apt-mark hold docker-ce docker-ce-cli docker-ce-rootless-extras containerd.io \
  docker-buildx-plugin docker-compose-plugin docker-model-plugin
DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=l apt-get upgrade -y \
  -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold
# докрутити docker окремо — і НЕ забути зняти hold, інакше він застрягне на старій версії
apt-mark unhold ... && apt-get install -y docker-ce docker-ce-cli containerd.io ...

# чи чекає ребут (unattended-upgrades ставить ядро сам)
cat /var/run/reboot-required.pkgs 2>/dev/null; uname -r
```

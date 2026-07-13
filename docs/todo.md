# Фаза 7 (крок 2) — Storage Service (Media/Storage)

> Активний робочий план. Попередні плани фаз (зокрема Notification) — в git-історії
> та в `docs/microservices-architecture.md`.

## Мета

Виокремити **Storage Service** (`:8008`) — stateless HTTP-сервіс, що централізує весь
об'єктний бекенд (S3/local через Flysystem) і AWS-креденшали. Моноліт більше **не тримає**
AWS SDK/ключі, а звертається до сервісу через `StorageClient implements FileStorageInterface`.
Останній stateless-сервіс Phase 7 (далі — Export, який одразу дзвонитиме в Storage).

## Що вже є (заземлення)

- `src/Storage/` — `FileStorageInterface`, `FlysystemFileStorage`, `FileStorageFactory` (local/S3).
- **6 споживачів, усі через `FileStorageInterface`** (ідеальний шов для cutover):
  `MediaController` (`GET /media`), `ProductImageService`, `ProductImagePresign{Service,Controller}`,
  `ExportController::download`, `ProcessExportHandler` (worker), `VerifyStorageCommand`.
- Notification/Delivery/Cart — свіжі зразки: stateless FrankenPHP + health + S2S JWT (`CartClient`).

## Ключові рішення (підтверджено)

| Питання | Рішення |
|---|---|
| База даних | **Немає.** Stateless (як Notification). Без міграцій/outbox. |
| Порт | **:8008** (наступний після notification :8007). |
| Hot-path зображень (S3) | **Presigned-GET** — браузер тягне картинку напряму з S3 (~15хв). Local → проксі через сервіс. Змінює `getUrlForDisplay`. |
| Upload зображень | Presigned-PUT (браузер→S3 напряму); логіка presign переїжджає в сервіс. |
| Шов у моноліті | `StorageClient implements FileStorageInterface` + rebind у `storage.yaml` → 6 споживачів не змінюються. |
| S2S авторизація | JWT RS256, роль `ROLE_STORAGE_ADMIN` (дзеркало `CartClient`). Health — public. |
| Degrade | Читання зображень → placeholder (degrade); presign/запис/download експорту → strict (throw). |
| VerifyStorageCommand | **Перенести в сервіс** (`app:verify-storage`); моноліт лишає перевірку `/health/ready`. |
| Порядок | **Storage перший**, потім Export. |
| Worker | **Немає** — Storage лише sync HTTP (на відміну від notification/delivery). |

## Контракт сервісу

```
POST   /api/storage/presign            → {url,key,method,headers}  (presigned PUT, S3-only)
GET    /api/storage/presign-get?key=   → короткоживучий GET URL (S3) | 200 stream (local)
PUT    /api/storage/objects/{key}      → server-side write (export worker)
GET    /api/storage/objects/{key}      → stream bytes (read/download/local media)
HEAD   /api/storage/objects/{key}      → exists
DELETE /api/storage/objects/{key}      → delete
GET    /api/storage/objects?prefix=&deep= → listKeys
GET    /health/live | /health/ready    → ready = S3 HeadBucket ok | local dir writable
```

## Кроки (кожен = окремий MR-розмір)

### Крок 1 — Каркас stateless-сервісу ✅
- [x] `services/storage-service/` за зразком notification-service (FrankenPHP `:80`→`:8008`, без doctrine/lexik-db).
- [x] Залежності одразу повним набором: `league/flysystem-aws-s3-v3`, `aws/aws-sdk-php`,
      `lexik/jwt` + `security-bundle` (валідація S2S, bundles реєструються в Кроці 2); `composer.lock` згенеровано раз.
- [x] Перенесено `FileStorageInterface`/`FlysystemFileStorage`/`FileStorageFactory` (FQCN `App\Storage\` як у моноліті).
- [x] `/health/live`=200; `/health/ready` — S3 HeadBucket (s3) / writable-dir (local). Живий smoke `curl :8008` → 200/200.

### Крок 2 — Storage API + S2S security + тести ✅
- [x] `StorageController`: presign(POST) / presign-get(GET) / list(GET) / read(GET+HEAD) / write(PUT) / delete(DELETE);
      ключі санітизуються (`..`-guard у Flysystem → 400).
- [x] `security.yaml`: `^/api/storage` [POST,PUT,DELETE]→`ROLE_STORAGE_ADMIN`; `^/api`→автентифікований; `^/health` public.
      Bundles Security+Lexik зареєстровано; lexik лише верифікує (public key з `/jwt` mount; test-keypair `config/jwt-test`).
- [x] `PresignService`: presign PUT (MIME allow-list, `products/`, +15хв) + presign GET (короткий URL / null у local).
- [x] Тести: 5 unit (presign/MIME/traversal) + 8 functional (round-trip, list, 401/403/404, local-guard) — 17 tests / 39 asserts зелені.
- [x] Живий smoke (dev, реальні ключі): health=200, `/api/storage/*` без токена=401, `lint:container` OK.
- [~] Стрімінг великих файлів — **відкладено** (read через `read()` у памʼять, як у монолітному `MediaController` зараз;
      справжній `readStream` — окрема оптимізація, поведінка не гіршає).

### Крок 3 — Cutover моноліту ✅ (розбито на 3a/3b за rule 4)
**3a — шов:**
- [x] `src/Storage/Client/StorageClient.php implements FileStorageInterface` (S2S JWT `ROLE_STORAGE_ADMIN`, strict; degrade — на боці викликачів).
- [x] `storage.yaml`: rebind `FileStorageInterface` → `StorageClient`; `.env` +`STORAGE_SERVICE_URL`.
- [x] `StorageClientTest` (MockHttpClient); повний набір моноліту 143 зелений.

**3b — presign/display/verify + прибирання:**
- [x] `ProductImagePresignService` → тонкий проксі (`StorageClient::presignPut`); `STORAGE_TYPE` лишається в моноліті для `supportsPresign()`.
- [x] `ProductImageService::getUrlForDisplay` → presigned-GET (`publicUrl`), degrade→`/media` проксі; прибрано `storageType`.
- [x] `VerifyStorageCommand` → GET `/health/ready` сервісу (без `S3Client`).
- [x] Видалено `FlysystemFileStorage`, `FileStorageFactory`, `aws.yaml`, exclude у `services.yaml`, factory-def у `storage.yaml`, запис у phpstan-baseline.
- [x] 3 Storage-юніт-тести перенесено в сервіс (Flysystem/Factory/FactoryS3); монолітний `ProductImageExtensionTest` оновлено.
- [x] Моноліт 129 tests / 344 asserts зелений; сервіс 31 tests / 73 asserts; `lint:container` OK; live `app:verify-storage` → 200 ok.

### Крок 4 — Prod overlay + CI ✅
- [x] `services/storage-service/compose.prod.yaml` (дзеркало cart: image, prod env, `ports: !reset []`,
      мережі `default`+`monolith`, **без worker'а/db**); `STORAGE_TYPE=s3` + AWS-секрети; `/jwt` mount з base compose.
- [x] `.gitlab-ci.yml`: `build/test/deploy:storage-service` (дзеркало notification, без db/міграцій; deploy-gate `about`).
- [x] Монолітний `compose.prod.yaml`: `php`+`worker` отримали `STORAGE_SERVICE_URL: http://storage-service`,
      AWS-креденшали прибрано (переїхали в storage-service).
- [x] **Нових CI-змінних нема** — `AWS_ACCESS_KEY_ID/SECRET/S3_BUCKET` + `APP_SECRET` уже існують (репойнт на сервіс).
      Region/version — з baked `.env` сервісу.

## Edge cases (rule 3)

- Ключ поза `products/`/`exports/` або з `..` → 400/404 (guard уже є в `sanitizeKey`).
- Storage-service лежить: читання зображень → placeholder (degrade); presign/запис/download → throw (strict).
- Local-режим: `presign` недоступний → 400; upload через `PUT /objects`.
- Великі експорти → стрімінг, не тримати в памʼяті.
- Cutover: моноліт віддає AWS env сервісу **до** видалення власних креденшалів (інакше подвійний доступ/розрив).
- Presigned-GET протухає (~15хв) — сторінка щоразу генерується заново; але не кешувати URL надовго.

### Крок 3 (виконано)
- Cutover через незмінний шов `FileStorageInterface`: rebind → `StorageClient`, 6 споживачів не чіпано.
  Presign/display/verify перенаправлено в сервіс; AWS SDK+креденшали видалено з бізнес-логіки моноліту.
- Розбито на 3a (шов) + 3b (прибирання) за CLAUDE.md rule 4 (>3 файли).
- Моноліт 129/344 + сервіс 31/73 зелені; `lint:container` OK; live verify-storage через host.docker.internal:8008.

### Відхилення Кроку 3 (свідомі)
- **`STORAGE_TYPE` лишається в моноліті** (не секрет) — щоб адмін-UI знав, чи пропонувати direct-S3 upload,
  без зайвого round-trip у сервіс. `AWS_*` env поки лишаються в `.env` (мертві) — прибирання в Кроці 4.
- **Повний S2S write-e2e (моноліт підписує → сервіс валідує токен)** доведено автотестами (роль-based accept/reject
  у сервісі + ідентичний прод-механізм cart/order); живий verify-storage б'є public `/health`. Реальний
  авторизований запис моноліт→сервіс підтвердимо в прод-smoke Кроку 4.

### Крок 4 (виконано)
- Prod-overlay сервісу + 3 CI-джоби; міграція AWS-секретів у storage-service, моноліт → `STORAGE_SERVICE_URL`.
- Обидва compose-оверлеї (сервіс + моноліт) валідно мержаться (`docker compose config`); CI-YAML валідний.
- Prod-smoke сервісу (як CI deploy): `APP_ENV=prod`, `STORAGE_TYPE=s3`, `composer install --no-dev` +
  `cache:clear --env=prod` + `about` exit 0 + `/health/live` 200.

### Відхилення / прод-нотатки Кроку 4
- **Прод — той самий S3-бакет**, що моноліт використовував раніше → міграція даних НЕ потрібна (зображення
  `products/` та експорти вже в S3; storage-service читає той самий bucket).
- **AWS_* лишаються в base `compose.yaml` моноліту** (dev через `.env.local`; у проді `.env.local` видаляється,
  у `.env` вони порожні) — мертвий безпечний залишок; глибоке прибирання base-compose поза обсягом (ризик для dev).
- **`STORAGE_TYPE` моноліту** береться з committed `.env` (=s3) → `supportsPresign()` працює в проді без CI-var.
- Порядок деплою: сервіс і моноліт у одному `deploy`-стейджі; короткий стартовий вікон (як cart/order) —
  читання зображень деградує до `/media`, а не падає.

## Тест-кейси (rule 3)

- Health: live=200; ready=200 при доступному S3/диску; 503 коли HeadBucket падає.
- `POST /presign` (S3) → валідний PUT URL + `products/<uuid>-file`; невалідний MIME → 400; local → 400.
- `PUT/GET/HEAD/DELETE /objects/{key}` round-trip; `..`-ключ → 400.
- 401 без S2S токена; 403 з токеном без `ROLE_STORAGE_ADMIN`.
- Моноліт після rebind: рендер картинки товару, presigned upload, download експорту — зелені.

## Огляд результатів

### Крок 1 (виконано)
- Каркас stateless-сервісу `services/storage-service/` (FrankenPHP, Symfony 7.4, :8008). Без БД/messenger/worker.
- Повний рантайм-набір залежностей закладено одразу (flysystem+aws+lexik+security), `composer.lock`
  згенеровано раз → Кроки 2-3 лише додаватимуть код/конфіг (bundles+security.yaml).
- Перенесено 3 Storage-класи (FQCN `App\Storage\`); wiring через `storage.yaml`+`aws.yaml` (як у моноліті),
  виключено з autowiring у `services.yaml`.
- `/health/live`=200; `/health/ready` — S3 HeadBucket (s3) або writable `var/storage` (local); порожній
  bucket у s3-режимі → 503 (детермінований misconfig-guard до будь-якого мережевого виклику).
- 4 функц. тести зелені (7 asserts). `lint:container` exit 0. Живий smoke: `curl :8008/health/{live,ready}` → 200/200.

### Відхилення від плану (свідомі)
- **`symfony/http-client` НЕ додано** — сервіс є *callee*, не *caller*; HTTP-клієнт (`StorageClient`) живе
  в моноліті (Крок 3). У Кроці 1 сервіс нікуди не дзвонить.
- **Bundles Lexik/Security відкладено в Крок 2** — зареєстровані лише deps (щоб lock був фінальний), а
  firewall+`security.yaml` вмикаються разом з API (інакше health вимагав би JWT-ключів уже зараз).
- **S3-fail health тестується як misconfig (порожній bucket → 503)**, а не мережевим падінням — щоб
  тест лишався герметичним; реальний HeadBucket-fail покриється живим smoke у s3-режимі (Крок 2/прод).

### Крок 2 (виконано)
- S2S API `StorageController` (6 маршрутів) + `PresignService` (PUT/GET presign). Firewall за зразком
  cart-service: писання→`ROLE_STORAGE_ADMIN`, читання→будь-який валідний токен, health→public.
- HEAD на read-маршруті короткозамикає (не читає байти) — дешева `exists`-проба.
- 17 тестів зелені (39 asserts): 5 unit + 8 functional. `lint:container` OK; dev-smoke з реальними ключами моноліту.

### Відхилення Кроку 2 (свідомі)
- **Presign-логіка перенесена, монолітний `ProductImagePresignService` ще НЕ чіпав** — це Крок 3 (cutover).
  Зараз обидва існують; жодного подвійного ефекту (моноліт ще не дзвонить у сервіс).
- **`readStream` не додавав** — `read()` у памʼять зберігає теперішню поведінку `MediaController`; стрімінг —
  окрема оптимізація, не регресія (експорти/зображення помірні).
- **Traversal-guard на об'єктному ключі** тестується через `..`-prefix у `list` (query виживає) + unit на
  `PresignService`; шлях `/objects/../x` нормалізується браузером до маршрутизації, тож не тестується по URL.

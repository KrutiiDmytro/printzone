# Фаза 5 — Checkout Saga зі stock-резервуванням (MVP) — ПЛАН, очікує апрув

> **Мета:** хореографічна Saga на наявному RabbitMQ-backbone. Монолітний Order емітить доменні події;
> **catalog-service стає консюмером** і веде `stock_reservations` (HELD→COMMITTED/RELEASED). Розблоковано
> Фазою 4.5 (cart/order на catalog-UUID). Гілка: `feat/phase5-checkout-saga`.
> **Order/Cart лишаються в моноліті** (емітять події) — не виносимо в окремі застосунки (обсяг).

## Розвідка (факт)
- Backbone: outbox → `OutboxRelay` → `events` (topic exchange, ключ `aggregate.eventName`) → консюмер.
- `OrderPaid` уже емітиться у `StripeWebhookController`. `OrderCreated`/`OrderCancelled` — ще нема.
- ⚠️ `events` транспорт серіалізує **PHP-native** → крос-сервісно не читається. Треба **JSON-контракт**.
- catalog-service: **нема ext-amqp / messenger**. RabbitMQ :5672 публічний (catalog → host.docker.internal).

## Рішення на узгодження
1. **Хореографія — одностороння** (рекоменд.): Order емітить `OrderCreated/OrderPaid/OrderCancelled`;
   Catalog резервує/комітить/звільняє. (Повна двостороння — Catalog шле `StockReserved/Failed`, Order
   реагує — не пасує до синхронного Stripe-redirect checkout; лишаємо як «емітимо для спостережуваності».)
2. **Серіалізація — JSON** + спільний контракт-клас `App\Messaging\Domain\IntegrationEvent` (дублюється
   в catalog-service з тим самим FQCN, щоб Messenger type-header змапився).
3. **Order/Cart — у моноліті** (емітять події), без виокремлення сервісів.

## Крок 1 — JSON-контракт подій (моноліт) ✅
- [x] `events` транспорт: `serializer: messenger.transport.symfony_serializer` (JSON на дроті)
- [x] ✅ Verify: relay→RabbitMQ→монолітний консюмер (OrderPaid лист) усе ще працює на JSON (purge старої черги).
      `EventSerializerRoundTripTest` (JSON-тіло + `type`=FQCN, decode→IntegrationEvent) + ручний E2E
      (outbox `OrderPaid`→relay→брокер→worker→лист у Mailpit `step1@printzone.test`)

## Крок 2 — Моноліт емітить Order-події ✅
- [x] `CheckoutController::pay`: після persist Order — `outboxRecorder->record('order','OrderCreated',
      {orderId,userId,items:[{productId,quantity}],totalAmount})` (в тій же транзакції). Знімок позицій —
      `Order::toEventItems()` (єдине джерело payload)
- [x] Stripe fail (catch + порожній url у `pay()` → `failOrder()`) / `StripeWebhookController` payment_failed:
      `OrderCancelled {orderId, items:[...]}` (реліз HELD)
- [x] ✅ Verify: `CheckoutControllerTest` — outbox містить `OrderCreated` (успіх) та `OrderCreated`+`OrderCancelled`
      (Stripe-відмова). relay публікує `{aggregate}.{eventName}` ⇒ `order.OrderCreated` (E2E доведено в Кроці 1).
      Повний сьют 148 OK; phpstan [OK] на змінених файлах

## Крок 3 — catalog-service: інфра консюмера + stock_reservations ✅
- [x] Dockerfile: + `amqp`; composer: `symfony/messenger` + `symfony/amqp-messenger` (+ `symfony/serializer` для JSON)
- [x] `events` транспорт (consume): власна черга `catalog_events` binding `order.*`; JSON-серіалізатор;
      контракт-клас `App\Messaging\Domain\IntegrationEvent` (той самий FQCN, що в моноліті)
- [x] `stock_reservations` (entity+міграція `Version20260618085405`): id, order_id(uuid), product_id(uuid),
      quantity, status (HELD/COMMITTED/RELEASED), created_at; unique (order_id, product_id) для ідемпотентності
- [x] compose: сервіс `catalog-worker` (messenger:consume events). ⚠️ host.docker.internal:5672 НЕ дістав брокер
      (IPv6 host-gateway quirk) → worker під'єднано напряму до мережі моноліту `task-25_default`, DSN `rabbitmq:5672`
- [x] ✅ Verify: worker піднявся й під'єднаний (172.19.0.11); черга `catalog_events` на брокері моноліту,
      binding `events`→`order.*` (mgmt API); `migrate`+`schema:validate [OK]`

## Крок 4 — Saga-хендлер (catalog-service) ✅
- [x] `OrderEventHandler` (#[AsMessageHandler] для IntegrationEvent):
      `OrderCreated`→reserve HELD (idempotent: skip якщо є для (order,product)),
      `OrderPaid`→COMMITTED + `products.stock -= qty` (лише HELD→не подвоюється),
      `OrderCancelled`→RELEASED (лише HELD). Емісію `StockReserved/Failed` назад — не робили (одностороння хореографія)
- [x] ✅ Verify: `OrderEventHandlerTest` 3/3 (HELD-дедуп; commit+списання один раз при повторі OrderPaid; release
      лишає stock). Повний сьют catalog-service **11 OK**. ⚠️ test-env `debug=false` → після нових сервісів `cache:clear --env=test`

## Крок 5 — E2E ✅
- [x] Локально (реальний шлях outbox→relay→RabbitMQ JSON→catalog-worker→`OrderEventHandler`→`db-catalog`):
      OrderCreated A(5)+B(3) → 2×**HELD**, stock 200 незмінний; OrderPaid A → **COMMITTED** + stock 200→**195**;
      OrderCancelled B → **RELEASED**, stock без змін. Рядки `stock_reservations` + `products.stock` звірені
- [x] phpunit моноліт **148 OK**, catalog-service **11 OK** (Saga 3); phpstan моноліт [OK] на змінених

## Підсумок Фази 5
Хореографічна checkout-Saga на наявному RabbitMQ-backbone. Моноліт емітить `OrderCreated/OrderPaid/
OrderCancelled` (JSON, спільний FQCN). catalog-service консюмить `order.*` і веде `stock_reservations`
(HELD→COMMITTED+`stock-=qty` / RELEASED), ідемпотентно за (order_id, product_id). Order/Cart лишились у
моноліті. Комміти: `96eb69e`(1) `2c6012d`(2) `836db48`(3) `c2ff22e`(4).

## Ризики (підсумок реалізації)
1. **Крос-сервісна серіалізація** — JSON + спільний FQCN; стару PHP-serialize чергу `events_all` purge'нуто.
   ⚠️ `events_all` (binding `#`) ловить усе → step-1 email-E2E не доводив routing-key; справжню перевірку
   дав `catalog_events` (binding `order.*`).
2. **Ідемпотентність** — relay at-least-once; unique (order_id,product_id) + переходи лише з HELD (повторний
   OrderPaid не подвоює списання) — покрито `OrderEventHandlerTest`.
3. **UUID-консистентність** — Фаза 4.5. ⚠️ `Uuid::isValid()` відкидає не-RFC-варіантні UUID (важливо для тест-даних).
4. **Мережа** — host.docker.internal:5672 НЕ дістав брокер (Docker Desktop IPv6 host-gateway) → worker
   приєднано до мережі моноліту `task-25_default`, DSN `rabbitmq:5672`.
5. **Кеш catalog-service** — після нових сервісів обовʼязково `cache:clear` (dev і test), інакше скомпільований
   контейнер у томі `csvc_var` не бачить хендлера/репозиторію.

---

# Фаза 4.5 — Catalog storefront cutover (catalog = джерело правди) — розблоковує Phase 5

> **Мета:** вітрина + кошик моноліту читають каталог із **catalog-service** (а не локального Doctrine).
> Кроки 1–4 зараз (розблоковують Saga: cart items нестимуть catalog-UUID). Адмінка (write-API + проксі) —
> крок 5, окремо. Гілка: `feat/phase4.5-catalog-cutover`. PrinterModel/printer-finder лишаються в моноліті.

## Звʼязність (з розвідки)
Вітрина (`ShopController` ×5 маршрутів, storefront `ProductController`, `SearchController`, Twig
`Brand/Category/ProductImage`) + `CartService` усі тримаються на Doctrine-каталозі. Вітрина+кошик мусять
перейти **разом** (інакше `CartService.add` не знайде продукт). Адмінка-write → крок 5.

## Крок 1 — catalog-service: канонічні дані + повний read-API ✅
- [x] Фікстури → канонічний набір (4 root + 10 child категорій, 7 брендів, 10 продуктів; slug-и = монолітні)
- [x] `CategoryRepository` (findBySlug, findAllRoot), `BrandRepository` (findBySlug, findAll)
- [x] `ProductRepository.findByFilters`: + `categorySlug, brandSlug, q (search), sort, availableOnly(stock>0)`;
      `getPriceRange()`; `findByIds()` (для кошика). `availableOnly` за замовч. false → Export (Phase 4) не ламається
- [x] `ProductController`: розширити список (slug-фільтри, sort, q, availableOnly, featured, ids, priceRange у відповіді);
      `CategoryController` (?root=1); новий `BrandController` (list). Без зміни схеми (міграція не потрібна)
- [x] ✅ Verify: fixtures:load; ендпоінти віддають дані; Export (Phase 4) усе ще зелений

## Крок 2 — Моноліт: CatalogClient + view-DTO ✅
- [x] `CatalogClient` (HttpClient + сервісний JWT, graceful-fallback на []) + `ProductView/CategoryView/
      BrandView` під геттери шаблонів (атрибути/category.products порожні — 1а/2а; children для навбару)

## Крок 3 — Cutover вітрини ✅
- [x] `ShopController`, storefront `ProductController`, `DashboardController` (home), Twig `Brand/Category`
      → `CatalogClient`. `SearchController`/printer-models/`brand_models` лишились у моноліті

## Крок 4 — Cutover CartService ✅
- [x] `CartService` (add + рендер) → `CatalogClient`; cart items несуть catalog-UUID ⇒ **розблоковано Saga**
- [x] `CheckoutController`/`StripeCheckoutService` оновлено під `ProductView`-знімок
- [x] Тести: CartServiceTest/CategoryExtensionTest мокають CatalogClient; CheckoutControllerTest стабить
      catalog ДО ініціалізації сервісу; baseline 98→92. **phpunit 147 зелений, phpstan [OK]**
- [x] ✅ E2E: home-сторінка моноліту рендериться з продуктами catalog-service (через docker-мережу)

## Підсумок Фази 4.5 (кроки 1–4)
Catalog-service — джерело правди для вітрини+кошика моноліту (read через HTTP). Адмінка поки пише в
моноліт (крок 5). Розблоковано справжню Фазу 5 (cart/order оперують catalog-UUID).

## Крок 5 (окремо) — Адмінка write-API + проксі (А)
- [ ] catalog-service write-API; 4 CRUD-контролери моноліту пишуть через HTTP; дроп каталог-таблиць моноліту

---

# Фаза 4 — Виокремлення Catalog Service (MVP, Strangler Fig крок 2) — ПЛАН, очікує апрув

> **Мета:** другий мікросервіс — Catalog. Окремий Symfony 7.4 застосунок `services/catalog-service/`
> (FrankenPHP), власна БД `db-catalog`, порт **8002**. Експонує **read-API** каталогу + health.
> Один показовий споживач моноліту переходить на HTTP. Гілка: `feat/phase4-catalog-service`.
> **Адитивно**: вітрина, кошик-рендер, адмінка поки читають каталог із моноліту.

## Розвідка (факт) — споживачі Catalog у моноліті
- **Вітрина (hot path):** `ProductController`, `ShopController`, `SearchController`, Twig
  (`BrandExtension.all_brands`, `CategoryExtension.get_categories/brand_models`, `ProductImageExtension`).
- **Кошик:** `CartService` — `find`/`findBy` Product на `add` + повний Product для рендеру (ціна/сток/назва/img).
- **Checkout:** `StripeCheckoutService` — line-items з product-обʼєктів кошика.
- **Export:** `ProductExtractor` — Doctrine QB прямо по `Product` (`leftJoin category`).
- **Адмінка:** `Product/Category/Brand/PrinterModel CrudController`, `DashboardController`.
- **Картинки:** `ProductImageService`, `ProductImagePresignService`, `MediaController` (S3 presign).
- Сутності: `Product, Category, Brand, PrinterModel, ProductAttribute` (інтра-Catalog FK, без крос-доменних).

## Рішення на узгодження (див. питання)
1. **Який споживач переводимо на HTTP** (показова межа). Рекомендація: **Export `ProductExtractor`** —
   ізольований, асинхронний, не hot-path, точковий read (розбіжність даних некритична). Відповідає §4.8 доку.
2. **stock_reservations** — у MVP **відкласти** (немає Order-Saga-споживача; додамо у Фазі 5).
3. **Runtime** — FrankenPHP (як user-service). **Admin/вітрина лишаються в моноліті.**

## Відомий компроміс (свідомо, як у Фазі 3)
catalog-service отримує власну `db-catalog`, засіяну тими ж фікстурами. Адмінка поки пише в каталог
**моноліту** → дані сервісу можуть розходитися. Для MVP прийнятно (показовий read через Export — точковий),
закриється коли адмінка/вітрина перейдуть на сервіс (наступні підфази).

## Під-задача 1 — Скелет catalog-service + інфра ✅
- [x] `services/catalog-service/` (патерни user-service: FrankenPHP, `db-catalog`+`catalog-service` :8002,
      vendor/var named-volumes), `/health/live|ready`
- [x] ✅ Verify: контейнер піднявся; health 200, 404 на невідомому маршруті

## Під-задача 2 — Домен + БД + read-API ✅
- [x] Сутності `Product/Category/Brand` (UUID PK; інтра-Catalog асоціації). PrinterModel/ProductAttribute —
      відкладено (вітринні фічі лишились у моноліті)
- [x] Міграція (через `diff` → коректні імена індексів/FK) + фікстури на `db-catalog`
- [x] Read-API: `GET /api/products` (фільтри + пагінація), `/products/{id}`, `/categories`; ready з пінгом БД
- [x] Service-to-service JWT: `^/api` верифікує підпис спільним keypair (lexik `jwt`-провайдер, без БД-юзерів)
- [x] ✅ Verify: `migrate`+`fixtures`+`schema:validate [OK]`; API віддає дані; токен зі спільного keypair прийнято

## Під-задача 3 — Перевести Export ProductExtractor на HTTP (моноліт) ✅
- [x] `src/Export/Client/CatalogProductClient` → `GET {CATALOG_SERVICE_URL}/api/products` (HttpClient,
      `auth_bearer` = сервісний JWT, timeout 5s)
- [x] `ProductExtractor` переписано: посторінкове читання через клієнт (PAGE_SIZE 500), не Doctrine
- [x] `.env`: `CATALOG_SERVICE_URL=http://host.docker.internal:8002`; клієнт через `#[Autowire]`
- [x] ✅ Verify: `ProductExtractorTest` (HTTP-мок) 5/5; phpstan [OK]; монолітний контейнер дістає сервіс
      (`host.docker.internal:8002/health/live` → ok). Повний export-E2E через адмінку — manual.

## Під-задача 4 — Тести сервісу + документація ✅
- [x] `phpunit` catalog-service: **OK (8 tests, 18 assertions)** — health(2), products(6: 401, list,
      featured-filter, pagination, get-by-id 200/404). SQLite + тестовий keypair (мінт сервісних токенів)
- [x] README сервісу + §10 Фаза 4 в architecture-доку відмічено
- [x] ✅ Verify: усе зелене

## Підсумок Фази 4
Catalog Service виокремлено (read-model: products/categories/brands), FrankenPHP, власна `db-catalog`, :8002,
read-API під service-to-service JWT (спільний keypair). Перший консюмер — монолітний Export — читає продукти
з сервісу по HTTP. Адитивно: вітрина/кошик/адмінка лишились на моноліті. Тести: сервіс 8, екстрактор 5.

## Залишок / наступні підфази
- Перевести вітрину/кошик-рендер на сервіс (великий крок, hot-path) + write-API/адмінка на сервісі.
- `stock_reservations` + Checkout Saga (Фаза 5: Cart+Order).
- Прод-розгортання обох сервісів (compose.prod + CI + секрети).

## Ризики
1. **Обсяг вітрини** — свідомо НЕ чіпаємо (лишається на моноліті); інакше переписування пів-додатка.
2. **Розбіжність даних** сервіс↔моноліт під час переходу (компроміс вище) — для Export некритично.
3. **Сервіс-до-сервіс auth** — моноліт→catalog потребує токен; перевикористати спільний JWT keypair
   (як у Фазі 3), згенерувати короткий сервісний токен.
4. **Продуктивність** — Export посторінково (limit 500), не одним запитом.

---

# Фаза 3 — Виокремлення User Service (MVP, Strangler Fig крок 1)

> **Мета:** перший справжній мікросервіс. Окремий Symfony-застосунок у `services/user-service/`
> (monorepo), власна БД `db-user`, порт **8001**. Видає JWT (RS256). Моноліт **довіряє** цим
> токенам, бо підпис тим самим keypair + ті самі fixture-користувачі.
> Гілка: `feat/phase3-user-service`. **Адитивно**: web/admin-сесії та OAuth поки лишаються в моноліті.

## Рішення (узгоджено)
- **Layout:** monorepo-підкаталог `services/user-service/`.
- **Обсяг:** MVP — сервіс owns identity API (register/login/JWT, `/api/users`, health). Моноліт майже не
  чіпаємо. Web/admin + OAuth — у моноліті (наступні підфази).
- **Gateway:** відкладено — сервіс на :8001, звертання напряму.
- **Runtime (моя рекомендація):** FrankenPHP — один контейнер на сервіс (стандарт сучасного Symfony,
  менше інфри, ніж php-fpm+nginx). Якщо волієш php-fpm+nginx як у моноліті — скажи.
- **JWT keypair:** монтуємо наявні ключі моноліту (`config/jwt/`) у сервіс read-only → обидва підписують/
  валідують однаковим RS256. Нуль змін у конфігу моноліту.

## Чому моноліт не змінюється
`^/api` фаєрвол моноліту вже `jwt: ~` (валідує підпис public-key'ем, тоді вантажить user по email-claim
з provider). Якщо токен підписаний тим самим private key і `sub`=email існує у БД моноліту (ті самі
фікстури `admin@example.com`/`user@example.com`) → моноліт приймає токен сервісу. Демонструє «trust».

## Під-задача 1 — Скелет сервісу + інфраструктура (greenfield, новий каталог) ✅
- [x] `services/user-service/`: composer.json (Symfony 7.4), Kernel, public/index.php, bin/console,
      config/{bundles,packages/*,routes,services}, .env (+ .env.local з JWT_PASSPHRASE, gitignored)
- [x] `Dockerfile` (**FrankenPHP**) + `compose.yaml`: `db-user` (postgres16), `user-service` (:8001),
      монтаж `../../config/jwt:/jwt:ro`
- [x] `HealthController`: `/health/live`, `/health/ready` (ping БД)
- [x] ✅ Verify: контейнер піднявся; `/health/live`→`{"status":"ok"}`, 404 на невідомому маршруті
- [x] ⚠️ **Фікс продуктивності:** `vendor/`+`var/` винесено в named-volumes — без цього кожен запит
      перевищував `max_execution_time` (десятки тис. stat() по 9p bind-mount; `about` падав 85с→2.7с)

## Під-задача 2 — User-домен + БД ✅
- [x] `src/Entity/User.php` (UUID PK, email unique, password nullable, roles JSON, fullName, googleId,
      githubId) + `UserRepository` (PasswordUpgrader) + `doctrine.yaml`
- [x] Міграція `users` (власна БД, public) + фікстури (admin/user — ті самі email, що в моноліті)
- [x] ✅ Verify: `migrate` + `fixtures:load` на `db-user`; `schema:validate` [OK]

## Під-задача 3 — Auth (register / login / users) ✅
- [x] LexikJWT (спільний keypair), `security.yaml`: `json_login` `/api/auth/login`, `^/` stateless jwt
- [x] `AuthController`: register (201) + login-stub-route; `UserController`: list (ADMIN) / get (self|ADMIN)
- [x] ⚠️ **Фікс:** `json_login` потребує маршрут на check_path (RouterListener@32 > Firewall@8), інакше 404
- [x] ✅ Verify: login→RS256 JWT (payload `username`=email+roles); `/api/users` 401/200; wrong pw 401

## Під-задача 4 — Cross-trust демо + документація ✅ (частково)
- [x] **Cross-trust доведено:** `openssl dgst -verify config/jwt/public.pem` токена сервісу → **Verified OK**;
      payload `username=admin@example.com` є в БД моноліту → моноліт прийме токен
- [x] `services/user-service/README.md`
- [ ] HTTP-демо проти моноліту: :80 на хості зайнятий локальним **Apache (XAMPP)**, не Docker-nginx —
      тому крос-trust показано криптографічно (еквівалентно й надійніше)
- [x] Автотести сервісу `phpunit` — **OK (14 tests, 27 assertions)**: health(2), auth(6: register 201/409/422×2,
      login JWT/401), users(6: 401/200/403, self/admin/forbidden). SQLite in-memory + окремий passphrase-free
      тестовий keypair (`config/jwt-test`) → самодостатньо, CI-ready
- [x] §10 Фаза 3 в `microservices-architecture.md` відмічено (MVP done; gateway/OAuth/cutover — відкладено)

## Підсумок Фази 3
User Service виокремлено як перший мікросервіс (FrankenPHP, власна БД, :8001), видає JWT, які моноліт
криптографічно приймає (спільний keypair). Адитивно — моноліт незмінний. 14 функц. тестів зелені.
Деплой свідомо відкладено (блокер: UUID-cutover у `develop` стирає прод-дані).

## Залишок / наступні підфази (потребують вибору напрямку)
- **Прод-розгортання user-service**: `compose.prod.yaml` (user-service + db-user + JWT-ключі), CI build/push,
  секрети (JWT_PASSPHRASE, DB пароль).
- **Фаза 4** — виокремлення Catalog Service (наступна за планом architecture-доку).
- Дрібніші підфази Фази 3: перенесення OAuth у сервіс, cutover web/admin моноліту, API Gateway.
- HTTP-демо crosс-trust проти моноліту — заблоковано хостовим Apache на :80 (доведено криптографічно).

## Ризики / підводні камені
1. **JWT identity claim**: монолітний lexik вантажить user по `sub`(email) з власної БД → email мусить
   збігатися у фікстурах обох. (Повний cut-over — коли моноліт перестане мати users; не цей крок.)
2. **Дублювання User-моделі** свідоме (Strangler): дві копії на час переходу — нормально.
3. **FrankenPHP** — новий runtime у проєкті; ізольований у services/, моноліт не чіпає.
4. **Порти/мережа Docker**: user-service і db-user в тій самій compose-мережі; :8001 назовні.
5. **Дані**: власна `db-user` — окремий том, не перетинається з монолітною БД.

---

# Фаза 2.2 — Database-per-service: розділення схем PostgreSQL

> **Мета:** завершити Фазу 2 з §10 architecture — кожен модуль отримує власну PostgreSQL-схему
> (namespace) в одній БД. Передумова фізичного database-per-service. UUID (2.1) і RabbitMQ (Фаза 2)
> вже зроблені; крос-доменні FK прибрані ще в Фазі 1 — тож **крос-схемних FK немає**.
> Гілка: `feat/phase2.2-db-schemas`. Підхід **data-preserving** (`ALTER TABLE ... SET SCHEMA`, НЕ cutover).

## Карта призначення схем
| Модуль | Схема | Таблиці |
|---|---|---|
| User | `users` | `users` (→ `users.users`) |
| Catalog | `catalog` | `categories`, `brands`, `products`, `printer_models`, `product_attributes` |
| Cart | `cart` | `carts`, `cart_items` |
| Order | `orders` | `orders`, `order_items` |
| Export | `exports` | `export_jobs` |
| Messaging | `messaging` | `outbox` |
| *(інфра — лишається в `public`)* | `public` | `messenger_messages`, `doctrine_migration_versions` |

> Схеми `payments`/`delivery` — НЕ створюємо: у моноліті немає їхніх сутностей (Payment слабко
> зв'язаний через `Order.stripeSessionId`+webhook). Створяться при виокремленні цих сервісів.

## Ключове відкриття (розвідка)
- **SQLite-емуляція схем увімкнена** (DBAL 3.10 `SQLitePlatform::emulateSchemaNamespacing`):
  `catalog.products` → `catalog__products`. Тести (`SchemaTool::createSchema` на SQLite) працюють
  прозоро — і DDL, і запити проходять однакову трансформацію. **Окрема гілка для тестів не потрібна.**
- Сирого SQL з іменами бізнес-таблиць немає (лише `pg_stat_statements_reset()`). Репозиторії — DQL.
- UUID PK → жодних сиквенсів (нема що переносити окремо; `SET SCHEMA` і так тягне owned-об'єкти).

## Під-задача 1 — Призначити схеми сутностям (атомарна; 12 файлів, 1 green-чекпоінт) ✅
> Один семантичний крок (як 2.1 під-задача 1): атрибут + міграція мусять лягти разом, інакше
> Postgres-схема розсинхронена. Перевищує правило «≤3 файли» — тому винесено в план на апрув.
- [x] Додати `schema: '<name>'` у `#[ORM\Table(...)]` усіх 12 сутностей за картою вище
- [x] Перевірити, що інтра-доменні асоціації (всі — у межах однієї схеми) лишаються валідні

## Під-задача 2 — Міграція (data-preserving, Postgres) ✅
- [x] Рукописна `Version20260616120000`: `CREATE SCHEMA IF NOT EXISTS` (×6) + `ALTER TABLE … SET SCHEMA`
- [x] `down()` реверсує: `SET SCHEMA public` (×12) + `DROP SCHEMA` (×6)
- [x] ⚠️ **НЕ** запускали `migrations:diff` — пишемо вручну (diff дав би деструктивний DROP/CREATE)
- [x] Інфра-таблиці (`messenger_messages`, `doctrine_migration_versions`) лишилися в `public`
- [x] **+8 `ALTER INDEX … RENAME`**: авто-імена індексів стали schema-qualified → diff хотів rename;
      додано в up()/down(), інакше `schema:validate` не в синхроні

## Під-задача 3 — Верифікація E2E ✅
- [x] `phpunit` зелений (149 OK, 369 assert) — SQLite через кастомний QuoteStrategy (див. нижче)
- [x] `phpstan` [OK] + `cs-fixer` 0 (новий файл LF)
- [x] Postgres: `migrate` → `schema:validate` [OK]; 6 схем (`\dn`); таблиці переїхали (catalog:5, cart:2,
      orders:2, users:1, exports:1, messaging:1); public лишив лише 2 інфра-таблиці
- [x] down→up roundtrip коректний; `fixtures:load` ок (products 10, users 2 у схемах)
- [ ] Manual E2E (каталог→кошик→`4242`→лист): покрито функціональним `CheckoutControllerTest`;
      повний ручний Stripe-прогін лишаю користувачу (інтерактивний redirect+webhook)

## ⚠️ Re-plan під час реалізації (workflow 1.2): SQLite-розрив ORM 3.6
**Проблема:** `no such table: users.users` у тестах. DBAL емулює схеми лише в DDL (`SchemaTool`
створює `users__users`), а **ORM 3.6 прибрав** емуляцію з DML — `DefaultQuoteStrategy::getTableName`
повертає `users.users`, що SQLite читає як `database.table`. DDL і DML розійшлися.
**Фікс:** `src/Doctrine/SchemaEmulatingQuoteStrategy.php` (extends Default) — на платформах без
`supportsSchemas()` (SQLite) повертає `schema__table`; на Postgres no-op. Реєстрація:
`doctrine.yaml → orm.quote_strategy`. Це відновлює нативну поведінку ORM 2.x.

## Ризики / підводні камені (підсумок)
1. **ORM 3.6 SQLite-schema gap** (головний, спіймали) — кастомний QuoteStrategy.
2. **diff деструктивний** для schema-move — тільки рукописна `SET SCHEMA`.
3. **Авто-імена індексів** schema-qualified → 8 `ALTER INDEX RENAME` у міграції.
4. **search_path / репліка / EasyAdmin / API Platform** — усе через schema-qualified метадані → ок.

## Review (виконано)
- ✅ 12 сутностей → 6 схем (`users`/`catalog`/`cart`/`orders`/`exports`/`messaging`); інфра в `public`.
- ✅ Data-preserving міграція (`SET SCHEMA`, не cutover) + 8 index-rename; up/down roundtrip зелений.
- ✅ Крос-схемних FK немає (спадок Фази 1) — нічого дропати.
- ✅ SQLite-тести: кастомний `SchemaEmulatingQuoteStrategy` (ORM 3.6 gap). phpunit 149 OK, phpstan [OK].
- ✅ `schema:validate` [OK] на реальному Postgres; репліка читає schema-qualified без змін.
- Схеми `payments`/`delivery` НЕ створені — нема сутностей у моноліті (створяться при виносі сервісів).

---

# Фаза 2.1 — Міграція PK: serial int → UUID (варіант А: чистий cutover)

> **Мета:** усі сутності переходять з int auto-increment на UUID PK, генерований у коді
> (передумова для розподілених БД у мікросервісах). Дані — фікстури, тож **чистий cutover**:
> змінюємо мапінг → пересоздаємо схему → перезаливаємо фікстури. Без поетапної міграції живих даних.
> Гілка: `feat/phase2.1-uuid-pk`. Інструмент: `symfony/uid` (вже встановлено) + `UuidType` (Doctrine bridge).
> **⚠️ Cutover дропає поточні дані прода (тестові замовлення) — прийнятно для навчального проєкту.**

## Карта впливу
- **12 сутностей** (всі int PK): `User`, `Cart`, `CartItem`, `Order`, `OrderItem`, `Product`, `Category`,
  `Brand`, `PrinterModel`, `ProductAttribute`, `ExportJob`, `OutboxMessage`.
- **Крос-доменні скалярні посилання** (стають `Uuid`): `Cart.userId`, `Order.userId`,
  `CartItem.productId`, `OrderItem.productId`.
- **Зовнішні дотики (критично):** Stripe `metadata.order_id` + `StripeWebhookController` (`(int)` cast),
  EasyAdmin CRUD (`IdField`), API Platform identifiers, Twig URL `path(..., {id})`.

## Під-задача 0 — Тулінг (1 коміт, green одразу)
- [ ] `config/packages/doctrine.yaml` → `dbal.types: uuid: Symfony\Bridge\Doctrine\Types\UuidType`
- [ ] Прибрати `identity_generation_preferences` (стане непотрібним для UUID; перевірити, що нічого не ламає)
- [ ] Верифікація: `cache:clear` ок, `phpunit` зелений (поведінка не змінилась)

## Під-задача 1 — Ядро: всі PK + посилання → Uuid
> Це **один атомарний** семантичний крок (крос-модульні посилання не дають розбити на ≤3 файли);
> розбиваю на логічні під-кроки, але **green-чекпоінт — наприкінці** під-задачі 2.
- [ ] Кожна entity: `id` → `#[ORM\Id] #[ORM\Column(type: 'uuid', unique: true)] private Uuid $id;`
      у конструкторі `$this->id = Uuid::v4();`, прибрати `#[ORM\GeneratedValue]`, `getId(): ?Uuid`
- [ ] Скалярні посилання `userId`/`productId` → тип `Uuid` (колонка `uuid`), сеттери/геттери оновити
- [ ] Інтра-Catalog асоціації (`Product.category/brand`, `PrinterModel.brand`, `ProductAttribute.product`)
      підхоплять UUID PK автоматично (Doctrine association) — перевірити мапінг

## Під-задача 2 — Споживачі (завершує green-чекпоінт)
- [ ] **`CartService`** ⚠️ зіставлення за `productId`: `Uuid` не порівнювати через `==` —
      використати `->equals()` або ключі `->toRfc4122()` (інакше кошик «загубить» товари)
- [ ] **`StripeWebhookController`** ⚠️: `(int) metadata.order_id` → `Uuid::fromString(...)`,
      `orderRepository->find($uuid)`; у `CheckoutController` metadata `order_id` = `(string) $order->getId()`
- [ ] Репозиторії (`findByUserId`, `findOneByUserId`, тощо): сигнатури `int` → `Uuid`
- [ ] Шаблони/контролери з `{id}` — `Uuid` має `__toString` (rfc4122), перевірити URL-и
- [ ] Верифікація: `phpunit` + `phpstan` зелені

## Під-задача 3 — Адмінка / API Platform / фікстури
- [ ] EasyAdmin CRUD: `IdField` для uuid → read-only (не редагується; генерується в конструкторі)
- [ ] API Platform: identifier = uuid (перевірити GET/collection)
- [ ] Фікстури: посилання через обʼєкти лишаються валідні; перевірити `fixtures:load`
- [ ] Верифікація: адмінка відкривається, `/api` віддає uuid

## Під-задача 4 — Міграція схеми (cutover)
- [ ] Згенерувати міграцію (`doctrine:migrations:diff`); зміна PK int→uuid з FK — деструктивна,
      тож якщо diff дає небезпечні ALTER — замінити на **drop & create** таблиць з uuid
- [ ] Перевірити uuid-тип у тестах на **SQLite** (in-memory) — `UuidType` має коректно зберігатись
- [ ] `migrate` на чистій БД + `fixtures:load` → дані з UUID

## Під-задача 5 — Верифікація E2E
- [ ] `phpunit` (усі) + `phpstan` [OK] + `cs-fixer` 0
- [ ] Локально: реєстрація → каталог → кошик → оплата `4242` → лист у Mailpit (увесь ланцюг на UUID)
- [ ] `doctrine:schema:validate` [OK]
- [ ] Деплой на `develop`; ⚠️ прод-БД пересоздається — перезалити фікстури на проді за потреби

## Ризики / підводні камені
1. **Uuid equality** у CartService — головний баг-ризик (порівняння обʼєктів).
2. **Stripe (int) cast** — зламає webhook → замовлення не стане PAID (ловили схоже раніше).
3. **SQLite uuid** у тестах — перевірити, бо прод Postgres має нативний `uuid`, а тести — SQLite.
4. **Прод втрата даних** — cutover дропає поточні тестові замовлення (свідомо прийнято).
5. **EasyAdmin IdField** на uuid — не робити editable.

## Review (виконано)
- ✅ Усі 12 сутностей + крос-посилання на `Uuid` (генерація `Uuid::v4()` у конструкторах).
- ✅ Споживачі: CartService (`->equals()`, рядкові ключі сесії), StripeWebhook (`Uuid::fromString`),
  репозиторії (`'uuid'`-тип), Export (рядкові message-id), маршрути (без `\d+`), екстрактори/ключі картинок.
- ✅ Тести оновлено; **phpunit 149 OK**, **phpstan [OK]** (baseline 111→98), cs-fixer 0.
- ✅ Cutover-міграція `Version20260615211500`: повний ланцюг (30 міграцій) + fixtures + `schema:validate` в синхроні на реальному Postgres; `orders.id`/`user_id` = нативний `uuid`.
- ⚠️ Деплой на прод **видалить прод-дані** і лишить таблиці порожніми (migrate не вантажить фікстури) — потрібен повторний `fixtures:load` на проді або сидінг.
- Комміти: `feffbe8` (тулінг), `c26852c` (ядро+тести), `e8f4218` (міграція).

---

# Фаза 2 — RabbitMQ + Transactional Outbox (event backbone)

> Адитивна інфраструктура: брокер RabbitMQ + outbox (атомарна публікація доменних подій).
> Демо: OrderPaid round-trip (webhook→outbox→relay→RabbitMQ→email). Гілка `feat/phase2-rabbitmq-outbox`.
> UUID НЕ чіпаємо (відкладено). Тести — `in-memory` транспорт (без брокера).

## Під-задача 1 — Інфраструктура RabbitMQ ✅
- [x] `Dockerfile.php`: `ext-amqp`; `symfony/amqp-messenger` (v7.4.11)
- [x] `compose.yaml`: сервіс `rabbitmq` (3.13-management) + volume; `compose.prod.yaml`: env + ports:[]
- [x] `.env`: `MESSENGER_EVENTS_DSN`; `messenger.yaml`: транспорт `events` (amqp topic)
- [x] `config/packages/test/messenger.yaml`: `async`+`events`→`in-memory`
- [x] Верифікація: rabbitmq healthy; `phpunit` (141) зелений

## Під-задача 2 — Outbox: таблиця + entity + recorder ✅
- [x] `OutboxMessage` entity + `Messaging` мапінг + міграція `outbox` (INT identity, index unpublished)
- [x] `OutboxRecorder` (persist без flush) + `OutboxMessageRepository::findUnpublished` (`@extends`)
- [x] Тести (`OutboxMessageTest`,`OutboxRecorderTest`); `phpunit` (144) + `phpstan` [OK] (baseline 109→110); mapping [OK]

## Під-задача 3 — Relay (outbox → RabbitMQ) ✅
- [x] `IntegrationEvent` DTO + `OutboxRelay` (publish→mark published, at-least-once; AmqpStamp routing key)
- [x] `app:outbox:relay` command (--time-limit loop) + сервіс `relay` у compose/prod; routing `IntegrationEvent→events`
- [x] `OutboxRelayTest`; `phpunit` (146) + `phpstan` [OK]; E2E: relay → exchange `events` у RabbitMQ, рядок published

## Під-задача 4 — Emit OrderPaid + consumer ✅
- [x] `StripeWebhookController`: на PAID `outboxRecorder->record('order','OrderPaid',...)` перед flush (атомарно)
- [x] `IntegrationEventHandler` (#[AsMessageHandler]) → email `order_paid.html.twig`; worker consume `async events`; queue `events_all` binding `#`
- [x] `IntegrationEventHandlerTest`; `phpunit` (149) + `phpstan` [OK] + `cs-fixer` 0
- [x] **E2E підтверджено**: outbox→relay→RabbitMQ→consumer→лист у Mailpit (To: e2e@printzone.test)

## Підсумок Фази 2
Event backbone готовий: RabbitMQ + Transactional Outbox + relay + consumer. Атомарна публікація
(закрито dual-write вікно). Адитивно, поведінка синхронних потоків незмінна. Тести на `in-memory`.

---

# Фаза 1 декомпозиції — розв'язати міждоменну зв'язність (моноліт)

> Замінити міждоменні Doctrine FK на скалярні int-посилання + знімки + подію. Один застосунок,
> поведінка незмінна. UUID відкладено на Фазу 2.1. Гілка `feat/phase1-decouple-modules`.
> Кожна під-задача — окремий коміт, між ними `phpunit` + `phpstan` зелені.

## Під-задача 1 — CartItem ⟂ Catalog ✅
- [x] `CartItem`: прибрати `product` ManyToOne → `productId:int` + знімки `productName`,`price`; `getTotal()` зі `price`
- [x] `CartService`: знімки на `add`; зіставлення за `productId`; `getCartFromDatabase()` масово вантажить продукти
- [x] `CartRepository::findOneByUser`: прибрати `leftJoin('i.product')`
- [x] Тести: `CartItemTest`, `CartTest`, `CartServiceTest`, `CheckoutControllerTest`
- [x] Міграція: `cart_items` +`product_name`,`price`, drop FK на `products`
- [x] Верифікація: `phpunit` (141) + `phpstan` зелені — коміт `48ecb81`

## Під-задача 2 — OrderItem ⟂ Catalog ✅
- [x] `OrderItem`: `product` ManyToOne → `productId:int` + `productName` (price вже є); `__toString` через `productName`
- [x] `CheckoutController`: `setProductId/setProductName` зі знімка кошика
- [x] `templates/admin/order/items.html.twig`: `item.productName`
- [x] Міграція + тести (`OrderTest`) — `phpunit` (141) + `phpstan` зелені

## Під-задача 3 — Order/Cart ⟂ User ✅
- [x] **3a** `Cart`: `user` → `userId:int` (unique); `CartRepository::findOneByUserId`; CartService — коміт `05581d3`
- [x] **3b** `Order`: `user` → `userId:int` + `userEmail` (знімок); CheckoutController; `OrderRepository::findByUserId`
- [x] **3b** `OrderExtractor`: прибрано `leftJoin('o.user')` → знімок `o.userEmail`
- [x] **3b** `OrderCrudController`: `AssociationField('user')`/`EntityFilter` → текст на `userEmail`
- [x] Міграції `carts`/`orders` + тести; baseline 111→109 — коміт `fb98664`

## Під-задача 4 — LoginListener → подія UserLoggedIn ✅
- [x] Подія `UserLoggedIn` (`src/User/Domain/Event/`) + диспатч із `LoginListener` (замість прямого CartService)
- [x] Слухач `MigrateGuestCartOnLogin` (`src/Cart/Application/EventListener/`) → міграція кошика
- [x] Тести: `LoginListenerTest` (диспатч) + `MigrateGuestCartOnLoginTest`; `phpunit` (142) + `phpstan` зелені

## Підсумок Фази 1
Усі міждоменні Doctrine FK розв'язані (Cart/Order ⟂ Catalog/User), синхронний виклик при вході →
подія. Моноліт цілий, поведінка незмінна. UUID — Фаза 2.1.

---

# Аудит і виправлення документації мікросервісів (Task 24)

> Звірка наявних `docs/microservices-architecture.md`, `docs/event-catalog.md`, `README.md` з фактичним
> кодом + виправлення розбіжностей. Лише документація — код застосунку не змінювався (3 файли).

## Виправлено
- [x] **H1** Inventory: додано таблицю `stock_reservations` + семантику HELD/COMMITTED/RELEASED; примітка в §3, чому інвентар co-located у Catalog
- [x] **H2** Stripe: Saga (§4.4) і Payment (§4.5) переписані під redirect-модель Checkout Session + webhook (не headless-списання); узгоджено діаграму в event-catalog
- [x] **H3** Статуси Order: enum приведено до коду — `PENDING, PAID, FAILED, PROCESSING, SHIPPED, DELIVERED, CANCELLED` (прибрано `PAYMENT_PENDING`, додано `FAILED`)
- [x] **H4** Міграція PK `serial int → UUID` винесена явним підкроком 2.1 у §10 з позначкою ризику
- [x] **M1** У схему Catalog додано `brands`, `printer_models`, `products.brand_id`
- [x] **M2** У §4.1 — примітка про код-гап: сутність `User` ще не має поля `githubId`
- [x] **M3** Додано підрозділ «Transactional Outbox» (§5) зі схемою таблиці; крос-посилання з §9
- [x] **M4** У §1/§2 і README позначено Delivery+Notification як greenfield, решту — як витягнуті модулі
- [x] **M5** У §4.3 — примітка, що гостьовий кошик сесія→БД є зміною поведінки
- [x] **L1** README: «22 типи подій» → 24 (дві згадки)
- [x] **L2** Вирівняно payload подій: `ProductCreated.categoryId`, `OrderCancelled.userId`, `PaymentRequested.{paymentId,idempotencyKey}`, `Payment*` providerTxId/providerCode
- [x] **L3** Домен виправлено: PrintZone друкарський магазин (не «електроніка»); заголовки узгоджено
- [x] **L4** Circuit Breaker/mTLS — додано конкретику механізму (ganesha / service mesh)

## Верифікація
- [x] `grep PAYMENT_PENDING docs/ README.md` → порожньо
- [x] enum статусів Order == `OrderCrudController.php:69-75` (+ FAILED)
- [x] схема Catalog містить усі 6 сутностей коду
- [x] `grep "22 тип|електроніки"` → порожньо

---

# Гігієна перед мікросервісами (Трек A)

> Підняти якість/відтворюваність коду перед виносом у мікросервіси. Без зміни звʼязності
> (Phase 1 не чіпаємо). Усі зміни поведінково нейтральні. Гілка `chore/hygiene-microservices-prep`.

## Задача 1 — Закріпити версії залежностей
- [x] `composer.json`: `stripe/stripe-php` `*`→`^20.2`, `doctrine/doctrine-fixtures-bundle` `*`→`^4.3.1`
- [x] `composer update` лише цих 2 пакетів (stripe v20.1→v20.2), `composer validate` ок

## Задача 2 — php-cs-fixer (єдиний стиль)
- [x] `composer require --dev friendsofphp/php-cs-fixer` (^3.95)
- [x] `.php-cs-fixer.dist.php` — Finder src+tests, `@Symfony` + `@PHP82Migration` (без risky)
- [x] `php-cs-fixer fix` один раз → 88 файлів переформатовано (окремий style-коміт)
- [x] `.gitlab-ci.yml` — job `cs:fixer` у validate (image ghcr.io/php-cs-fixer, `check`)

## Задача 3 — PHPStan + symfony extension
- [x] `composer require --dev phpstan/phpstan phpstan/phpstan-symfony phpstan/extension-installer`
- [x] `phpstan.dist.neon` — level 6, paths src+tests, containerXmlPath, includes baseline
- [x] згенерувати `phpstan-baseline.neon` (111 помилок у baseline)
- [x] `.gitlab-ci.yml` — job `static:analysis` у validate (composer:2: install→warmup→analyse)

## Верифікація
- [x] `php-cs-fixer check` → 0 порушень
- [x] `phpstan analyse` → [OK] No errors (з baseline)
- [x] `phpunit` → OK (141 tests, 348 assertions) — поведінка не змінилась

## Review

### Що зроблено
1. **Версії**: `stripe/stripe-php *`→`^20.2`, `doctrine-fixtures-bundle *`→`^4.3.1` (відтворювані білди).
2. **php-cs-fixer** ^3.95: `@Symfony` + `@PHP82Migration` (non-risky), 88/111 файлів переформатовано.
3. **PHPStan** 2.2 + phpstan-symfony: level 6, baseline 111 помилок (CI зелений; борг знижуємо «храповиком»).
4. **CI**: дві нові job-и у стадії `validate` — `cs:fixer` (check) і `static:analysis` (phpstan).

### Структура комітів
- `style:` — лише переформатування `src/`+`tests/` (механічний diff, ізольований для рев'ю).
- `chore(quality):` — тулінг, конфіги, baseline, CI, закріплення версій (composer.json/lock
  переплетені між задачами → не діляться по файлах, тому згруповані).

### Поза обсягом (свідомо, не гігієна)
Ідемпотентність webhook, Phase 1 розв'язання FK, розбиття CartService, зняття типів з baseline.

### Як знижувати PHPStan-борг далі
`phpstan-baseline.neon` (111) — додавати типи generics (Doctrine Collections), `TEntity`
в EasyAdmin CRUD, прибирати застарілі `int|null` на `$id`. Видаляти записи з baseline по мірі фіксу.

---

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

---

# Stripe Checkout Integration (EUR)

## Архітектура флоу

```
[Checkout form] → POST /checkout/pay
    │
    ▼
CheckoutController::pay()
    │  створює Order (status: PENDING)
    │  зберігає stripe_session_id в Order
    ▼
StripeCheckoutService::createSession(Order)
    │  line_items з cart (ціни вже в центах)
    │  success_url: /checkout/success?session_id={CHECKOUT_SESSION_ID}
    │  cancel_url: /checkout/cancel
    ▼
redirect → Stripe hosted page
    │
    ├─ [Успіх] → GET /checkout/success → показує сторінку подяки
    │
    └─ [Webhook] POST /stripe/webhook
           │  перевіряє Stripe-Signature
           │  checkout.session.completed event
           ▼
       Order.status = PAID (надійне підтвердження)
```

## Підзадачі

### Фаза 1 — Setup (≤3 файли)
- [x] 1.1 `composer require stripe/stripe-php`
- [x] 1.2 `.env` — додати `STRIPE_SECRET_KEY`, `STRIPE_PUBLISHABLE_KEY`, `STRIPE_WEBHOOK_SECRET`
- [x] 1.3 `src/Order/Domain/Entity/Order.php` — додати поле `stripeSessionId` (nullable string)
- [x] 1.4 Doctrine migration для нового поля

### Фаза 2 — Service + Controller (≤3 файли)
- [x] 2.1 `src/Payment/Service/StripeCheckoutService.php` — новий сервіс, створює Stripe Checkout Session
- [x] 2.2 `src/Controller/CheckoutController.php` — переробити `placeOrder` → `pay()`, редірект на Stripe

### Фаза 3 — Webhook + Security (≤2 файли)
- [x] 3.1 `src/Controller/StripeWebhookController.php` — POST `/stripe/webhook`, верифікація підпису, Order→PAID
- [x] 3.2 `config/packages/security.yaml` — вивести `/stripe/webhook` з-під CSRF

### Фаза 4 — Шаблони (≤2 файли)
- [x] 4.1 `templates/checkout/success.html.twig` — сторінка успішної оплати
- [x] 4.2 `templates/checkout/cancel.html.twig` — сторінка скасування

## Ключові деталі реалізації

| Деталь | Рішення |
|--------|---------|
| Ціни | `price` в БД — вже в центах (int). Stripe теж приймає центи → конвертація не потрібна |
| Валюта | `eur` |
| `stripeSessionId` | зберігається в Order до редіректу; webhook шукає Order по цьому полю |
| Webhook безпека | `Stripe::constructEvent()` з `STRIPE_WEBHOOK_SECRET` верифікує підпис |
| CSRF | Webhook endpoint виключений (`stateless: true` або `security: false`) |
| Локальне тестування | `stripe listen --forward-to host.docker.internal/stripe/webhook` |

## Тестові картки Stripe
- `4242 4242 4242 4242` — успішна оплата
- `4000 0000 0000 9995` — declined

## Edge cases
- Порожній кошик → redirect на `/cart` (вже є)
- Stripe session expired → повторний checkout (cancel_url поверне на `/cart`)
- Webhook fires після redirect success — порядок не гарантований, тому статус PAID ставиться лише вебхуком
- Дублювання вебхуків — ідемпотентна обробка (if Order.status !== PAID → set PAID)

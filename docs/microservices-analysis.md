# Аналіз проєкту: декомпозиція моноліту PrintZone у мікросервіси

> **Мета.** Стратегічно поділити наявний e-commerce-застосунок PrintZone на мікросервісну
> архітектуру, виділивши основні сервіси, що відповідають ключовим бізнес- і технічним потребам.
> Документ структуровано за чотирма етапами завдання: **Аналіз і планування → Межі сервісів (DDD) →
> Дизайн сервісів → Документація взаємодій**.
>
> Цей файл — **аналітичний огляд із обґрунтуванням рішень**, спертий на фактичний код. Повні
> API-контракти, SQL-схеми й payload подій винесено в:
> - [`microservices-architecture.md`](./microservices-architecture.md) — детальний дизайн усіх сервісів;
> - [`event-catalog.md`](./event-catalog.md) — каталог 24 асинхронних подій.

---

## 1. Аналіз і планування (Analysis & Planning)

### 1.1 Поточна архітектура (As-Is)

PrintZone — **модульний моноліт** на Symfony 7.4 (PHP 8.2+), друкарський магазин (картриджі, тонери,
drum units, стрічки). Стек: PostgreSQL 16 (primary + read-replica), API Platform (REST), EasyAdmin
(адмінка), LexikJWT (API-авторизація) + OAuth 2.0 (Google/GitHub) для web, League Flysystem (S3/local),
Symfony Messenger (async). Уже застосовано DDD-конвенції: кожен домен має `Domain/Entity/`.

```
src/
├── Catalog/  → Product, Category, Brand, PrinterModel, ProductAttribute
├── Cart/     → Cart, CartItem (+ CartService: гість → сесія, авторизований → БД)
├── Order/    → Order, OrderItem (статуси PENDING→PAID/FAILED→...→DELIVERED)
├── User/     → User (OAuth Google/GitHub, ролі)
├── Payment/  → StripeCheckoutService + StripeWebhookController
├── Export/   → ExportJob (async CSV/JSON/XML; extractors + formatters)
└── Storage/  → FileStorageInterface (local/S3 через FileStorageFactory)
```

**Технічна болячка №1 — спільна БД.** Усі модулі ділять одну схему PostgreSQL з FK між доменами
(див. §1.3). Це головна перешкода незалежного деплою.

### 1.2 Інвентаризація доменних сутностей (з кодом)

| Сутність | Модуль | Ключові поля | Файл |
|---|---|---|---|
| `Product` | Catalog | name, price (центи), **stock**, image, isFeatured | `src/Catalog/Domain/Entity/Product.php` |
| `Category` | Catalog | name, slug, parent (self-ref дерево) | `Category.php` |
| `Brand` | Catalog | name | `Brand.php` |
| `PrinterModel` | Catalog | name, brand (FK) | `PrinterModel.php` |
| `ProductAttribute` | Catalog | name, value, product (FK) | `ProductAttribute.php` |
| `Cart` / `CartItem` | Cart | user (FK), items; product (FK), quantity | `src/Cart/Domain/Entity/` |
| `Order` | Order | user (FK), status, totalAmount, **stripeSessionId** | `src/Order/Domain/Entity/Order.php` |
| `OrderItem` | Order | product (FK), quantity, **price (snapshot)** | `OrderItem.php` |
| `User` | User | email, roles, password, fullName, googleId | `src/User/Domain/Entity/User.php` |
| `ExportJob` | Export | type, format, status, filePath, requestedBy | `src/Export/Domain/Entity/ExportJob.php` |

### 1.3 Карта міжмодульної зв'язності (FK) — що ускладнює розрізання

| Зв'язок | Тип | Наслідок для декомпозиції |
|---|---|---|
| `Cart.user → User` | OneToOne | Cart залежить від User |
| `CartItem.product → Product` | ManyToOne | Cart залежить від Catalog |
| `Order.user → User` | ManyToOne | Order залежить від User |
| `OrderItem.product → Product` | ManyToOne | Order залежить від Catalog |
| `Product.category/brand`, `PrinterModel.brand` | ManyToOne | внутрішні до Catalog (не проблема) |
| `ProcessExportHandler` → Product/Order/User extractors | прямі запити | Export читає всі домени напряму |
| `LoginListener` → `CartService` | синхронний виклик | міждоменна синхронна зв'язність при вході |

**Що вже добре розв'язано в коді (спирається дизайн):**
- `OrderItem.price` — **snapshot ціни** на момент покупки (`OrderItem.php:42`): Order уже не залежить
  від поточної ціни Catalog.
- Payment **слабко зв'язаний**: через `Order.stripeSessionId` + webhook + `metadata.order_id`
  (`StripeCheckoutService.php`, `StripeWebhookController.php`) — це готова межа сервісу.
- Async-інфраструктура присутня (`config/packages/messenger.yaml`): є куди вмонтувати подієву шину.

### 1.4 Сегменти, придатні до виділення (кандидати в сервіси)

Критерії відбору: (1) чітка бізнес-відповідальність; (2) власні дані; (3) різні профілі
навантаження/масштабування; (4) можливість незалежного деплою.

| Кандидат | Підстава | Походження |
|---|---|---|
| **User Management** | окрема відповідальність (identity/auth), найменша зв'язність | з модуля `User` |
| **Product Catalog** | ядро читання, високе навантаження на читання | з модуля `Catalog` |
| **Order Processing** | складний бізнес-процес зі станами, оркестрація | з модуля `Order` |
| **Inventory** | запаси/резервування — окрема технічна потреба (див. §2.4) | поле `Product.stock` |
| **Cart** | гібридне сховище (сесія/БД), сплеск перед checkout | з модуля `Cart` |
| **Payment** | фінансовий домен, ізоляція PCI, інтеграція Stripe | з модуля `Payment` |
| **Export/Reporting** | важкі async-задачі, окремий ресурсний профіль | з модуля `Export` |
| **Media/Storage** | stateless-проксі до S3/local | з модуля `Storage` |
| **Delivery**, **Notification** | майбутні домени (greenfield) | нові |

---

## 2. Межі сервісів (Service Boundaries / DDD)

### 2.1 Bounded contexts

Кожен сервіс = один **обмежений контекст** із власною моделлю даних і мовою домену. Сутність ніколи
не «перетікає» між контекстами як спільний клас — лише через API або події.

### 2.2 Context map (відношення)

```
                ┌────────────────────────┐
                │   Identity (User)       │  Upstream для всіх
                │   видає JWT (RS256)     │
                └───────────┬────────────┘
                  JWT claims (userId, roles)
        ┌───────────────────┼─────────────────────┐
        ▼                   ▼                     ▼
  ┌──────────┐  HTTP  ┌──────────┐  snapshot ┌──────────┐  events  ┌──────────┐
  │ Catalog  │◄───────│   Cart   │──────────►│  Order   │─────────►│ Payment  │
  │ (+stock) │        └──────────┘           └────┬─────┘          └────┬─────┘
  └──────────┘                                    │ events              │ webhook
        ▲ events (reserve/release)                ▼                     ▼
        └───────────────────────────────►  (Saga compensation)    (Stripe)
```

- **Identity** — *upstream* (Open Host Service): видає JWT, решта лише валідує підпис.
- **Cart → Catalog** — *Customer/Supplier*: Cart синхронно питає ціну/наявність при додаванні товару.
- **Order → Catalog/Payment** — через **події** (Saga), без прямих FK.
- **Anti-Corruption Layer** — Export і нові сервіси читають інші домени лише через published API/події,
  не імпортуючи їхні моделі.

### 2.3 Таблиця власності даних (database-per-service)

| Таблиці | Власник-сервіс |
|---|---|
| `users` | Identity |
| `categories`, `brands`, `products`, `printer_models`, `product_attributes`, `stock_reservations` | Catalog |
| `carts`, `cart_items` | Cart |
| `orders`, `order_items` | Order |
| `payments`, `refunds` | Payment |
| `export_jobs` | Export |
| `shipments`, `tracking_events` | Delivery (новий) |
| — (stateless) | Notification, Media/Storage |

**Правила ізоляції:** жодних крос-сервісних JOIN-ів і FK; посилання — лише UUID; потрібні чужі дані →
зберігаємо **знімок** (як `OrderItem.price`/`product_name`).

### 2.4 Стратегічні рішення

- **Inventory залишено всередині Catalog**, бо `stock` і життєвий цикл продукту змінюються разом —
  окремий сервіс дав би більше міжсервісних викликів, ніж користі. Але **облік резервувань** винесено в
  таблицю `stock_reservations` (стан HELD/COMMITTED/RELEASED), інакше Checkout Saga не має чим тримати
  «зарезервовано-не-оплачено» й компенсувати. За потреби незалежного масштабування інвентар
  виноситься окремим сервісом без зміни контрактів подій.
- **Снапшоти замість FK:** `order_items`/`cart_items` зберігають `product_name`+`price` на момент дії.
- **Гостьовий кошик:** сесія → рядок у `carts` (з `session_id`) — робить Cart stateless щодо застосунку.

---

## 3. Дизайн сервісів (Service Design)

Нижче — стислий зріз кожного сервісу. **Повні API-ендпойнти, SQL-схеми й payload подій —** у
[`microservices-architecture.md`](./microservices-architecture.md) (§4) та
[`event-catalog.md`](./event-catalog.md).

| Сервіс | Відповідальність | Ключові API | Дані | Публікує події | Споживає події |
|---|---|---|---|---|---|
| **Identity** | реєстрація, login, JWT, OAuth, профілі | `POST /auth/login`, `/auth/refresh`, `GET /users/{id}` | users | `UserRegistered`, `UserLoggedIn` | — |
| **Catalog** | продукти/категорії/бренди, резервування запасів | `GET /products`, `GET /categories`, `POST /products/{id}/presign` | products, categories, brands, printer_models, product_attributes, stock_reservations | `Product*`, `StockReserved`, `StockReservationFailed`, `StockReleased` | `OrderCreated`, `OrderPaid`, `OrderCancelled` |
| **Cart** | кошик (гість+авторизований), міграція | `GET /cart`, `POST /cart/items`, `GET /cart/summary` | carts, cart_items | `CartCleared`, `CartMigrated` | `UserLoggedIn`, `ProductUpdated` |
| **Order** | створення замовлень, статуси, оркестрація Saga | `POST /orders`, `GET /orders/{id}`, `PUT /orders/{id}/status` | orders, order_items | `OrderCreated`, `PaymentRequested`, `OrderPaid`, `OrderCancelled` | `StockReserved/Failed`, `PaymentSucceeded/Failed` |
| **Payment** | Stripe Checkout, webhook, повернення | `POST /payments`, `POST /webhooks/stripe` | payments, refunds | `PaymentSucceeded/Failed`, `RefundProcessed` | `PaymentRequested` |
| **Export** | async CSV/JSON/XML, витяг даних | `POST /exports`, `GET /exports/{id}/download` | export_jobs | `ExportJobCompleted/Failed` | — |
| **Media/Storage** | файли S3/local, presigned URL | `PUT/GET /storage/{key}`, `POST /storage/presign` | — (stateless) | — | — |
| **Delivery** (новий) | відправлення, трекінг | `POST /shipments`, `GET /shipments/{id}/tracking` | shipments, tracking_events | `Shipment*`, `TrackingUpdated` | `OrderPaid` |
| **Notification** (новий) | email/SMS/push | — (лише consumer) | — (stateless) | — | майже всі бізнес-події |

**Loose coupling та незалежний деплой досягаються через:**
1. database-per-service (немає спільної схеми);
2. асинхронні події (RabbitMQ topic exchanges) для зміни стану + Saga з компенсаціями;
3. синхронний HTTP лише там, де потрібна свіжа відповідь (Cart→Catalog ціна), із Circuit Breaker;
4. контракти подій версіонуються незалежно від внутрішніх схем;
5. кожен сервіс має `GET /health/live` + `/health/ready` для оркестратора.

---

## 4. Взаємодія та документація (Documentation)

### 4.1 Комунікація: sync vs async

- **Sync (HTTP):** Cart→Catalog (валідація/ціна при `add`), Order→Cart (`/cart/summary` при checkout),
  Export→{Catalog,Order,User} (посторінковий витяг), будь-хто→Storage.
- **Async (RabbitMQ):** усі зміни стану як події; гарантія доставки — **Transactional Outbox** +
  ідемпотентні консюмери (дедуплікація за `eventId`).

### 4.2 Ключовий потік: Checkout Saga (хореографія)

```
Order створює Order(PENDING) ─OrderCreated─► Catalog резервує (stock_reservations=HELD)
   ├─ StockReserved ─► Order публікує PaymentRequested
   │     └─ Payment створює Stripe Checkout Session → checkoutUrl → редирект → webhook
   │           ├─ PaymentSucceeded ─► Order=PAID ─► Catalog: резерв COMMITTED, stock−
   │           └─ PaymentFailed    ─► Order=FAILED ─► OrderCancelled ─► Catalog: резерв RELEASED
   └─ StockReservationFailed ─► Order=CANCELLED (компенсація не потрібна)
```

> Оплата інтерактивна (redirect-модель Stripe Checkout), підтвердження — **webhook'ом**, а не
> headless-подією. Повна діаграма — у [`event-catalog.md`](./event-catalog.md).

### 4.3 Cross-cutting

| Аспект | Рішення |
|---|---|
| Авторизація | JWT RS256 (Identity видає, інші валідують підпис); сервіс-до-сервіс — окремі короткі токени |
| Шлюз/агрегація | BFF на клієнт (Mobile/Desktop/Public) + API Gateway (маршрутизація, rate-limit) |
| Спостережуваність | трасування `X-Trace-Id` (Jaeger), логи (Loki), метрики (Prometheus) |
| Надійність | Outbox, Circuit Breaker (ganesha / service mesh), retry у Messenger |
| Стратегія міграції | **Strangler Fig**: розв'язати FK → database-per-service (+ serial→UUID) → виносити сервіси по черзі (Identity → Catalog → Cart+Order+Saga → Payment+Delivery → Export+Notification+Storage) |

### 4.4 Пов'язані документи

- [`microservices-architecture.md`](./microservices-architecture.md) — повний дизайн (API, SQL, Saga,
  health-checks, безпека, 7-фазна міграція).
- [`event-catalog.md`](./event-catalog.md) — 24 події зі схемами payload і sequence-діаграмами.
- [`STORAGE_SETUP.md`](./STORAGE_SETUP.md) — налаштування S3/local.

---

## 5. Висновки

1. PrintZone уже структурований за DDD — межі контекстів проглядаються з модулів `src/*`, що **знижує
   ризик** декомпозиції.
2. Головна перешкода — **спільна БД з міждоменними FK** (4 ключові зв'язки). План: снапшоти + UUID +
   події замість FK.
3. Дві межі вже фактично готові: **Payment** (слабкозв'язаний через Stripe session/webhook) і
   **Export** (async + окремий ресурсний профіль).
4. **Inventory** свідомо лишається в Catalog, але з окремою таблицею резервувань — компроміс між
   когезією й потребами Saga.
5. Рекомендований порядок виносу — за зростанням зв'язності (Identity → Catalog → Order/Cart/Payment),
   через Strangler Fig, без простою.

# Мікросервісна Архітектура — PrintZone (Task 24)

## Зміст

1. [Поточний стан (As-Is)](#1-поточний-стан-as-is)
2. [Цільова архітектура (To-Be)](#2-цільова-архітектура-to-be)
3. [Межі сервісів (DDD Bounded Contexts)](#3-межі-сервісів-ddd-bounded-contexts)
4. [Дизайн сервісів](#4-дизайн-сервісів)
   - [4.1 User Service](#41-user-service)
   - [4.2 Catalog Service](#42-catalog-service)
   - [4.3 Cart Service](#43-cart-service)
   - [4.4 Order Service](#44-order-service)
   - [4.5 Payment Service](#45-payment-service)
   - [4.6 Delivery Service](#46-delivery-service)
   - [4.7 Notification Service](#47-notification-service)
   - [4.8 Export Service](#48-export-service)
   - [4.9 Storage Service](#49-storage-service)
5. [Міжсервісна комунікація](#5-міжсервісна-комунікація)
6. [BFF шар](#6-bff-шар)
7. [Health Check API](#7-health-check-api)
8. [Спостережуваність](#8-спостережуваність)
9. [Безпека](#9-безпека)
10. [Стратегія міграції](#10-стратегія-міграції)

---

## 1. Поточний стан (As-Is)

Додаток PrintZone є **модульним монолітом** (Symfony 7.4), вже організованим за DDD-принципами. У коді присутні шість обмежених контекстів (+ інфраструктурний Storage), що спільно використовують одну базу даних PostgreSQL та один асинхронний воркер.

```
Єдиний Symfony додаток
    ├── src/Catalog/    → Продукти, Категорії, Бренди, Моделі принтерів, Атрибути
    ├── src/Cart/       → Кошик, Елементи кошика (гість → сесія, авторизований → БД)
    ├── src/Order/      → Замовлення, Елементи замовлення
    ├── src/User/       → Користувачі, OAuth (Google, GitHub)
    ├── src/Payment/    → Stripe Checkout (StripeCheckoutService + webhook)
    ├── src/Export/     → Завдання експорту (async CSV/JSON/XML)
    └── src/Storage/    → FileStorageInterface (S3 / Local)

Єдина PostgreSQL (всі таблиці в одній схемі)
Єдиний Messenger Воркер (всі черги: export, mail, SMS)
Єдиний S3 Bucket (зображення продуктів + файли експорту)
```

> **Походження цільових сервісів.** User, Catalog, Cart, Order, Payment, Export і Storage **витягуються з наявних модулів** коду. Сервіси **Delivery** і **Notification** — **нові (greenfield)**: у поточному моноліті немає окремих доменів доставки чи сповіщень (сповіщення зараз надсилаються інлайн через Symfony Mailer, напр. у `ProcessExportHandler`). Вони включені як цільові межі, а не як витягнуті модулі.

### Точки зв'язності для усунення

| Зв'язність | Поточна реалізація | Проблема |
|---|---|---|
| `order_items.product_id` → `products` | Doctrine FK, спільна БД | Жорстка міждоменна залежність |
| `cart_items.product_id` → `products` | Doctrine FK, спільна БД | Жорстка міждоменна залежність |
| `orders.user_id` → `users` | Doctrine FK, спільна БД | Всі домени залежать від таблиці користувачів |
| `carts.user_id` → `users` | Doctrine FK, спільна БД | Всі домени залежать від таблиці користувачів |
| `ProcessExportHandler` → всі репозиторії | Прямі Doctrine запити | Експорт читає всі домени напряму |
| `LoginListener` → `CartService` | Синхронний виклик | Міждоменна синхронна зв'язність |

---

## 2. Цільова архітектура (To-Be)

### Загальна схема системи

```
┌──────────────┐  ┌───────────────┐  ┌──────────────┐
│  BFF Mobile  │  │  BFF Desktop  │  │  BFF Public  │
│   (порт 8010)│  │   (порт 8011) │  │  (порт 8012) │
└──────┬───────┘  └───────┬───────┘  └──────┬───────┘
       └──────────────────┼──────────────────┘
                          │ HTTP / JWT
              ┌───────────▼───────────┐
              │      RabbitMQ         │  ← асинхронні події
              └───────────┬───────────┘
       ┌──────────────────┼───────────────────────┐
       │        │         │          │            │
┌──────▼──┐ ┌───▼────┐ ┌──▼─────┐ ┌──▼──────┐ ┌───▼─────┐
│  User   │ │Catalog │ │  Cart  │ │  Order  │ │ Payment │
│ Service │ │Service │ │Service │ │ Service │ │ Service │
│  :8001  │ │ :8002  │ │ :8003  │ │  :8004  │ │  :8005  │
└──────┬──┘ └───┬────┘ └──┬─────┘ └──┬──────┘ └───┬─────┘
       │        │         │          │            │
  БД:users БД:catalog  БД:cart   БД:orders    БД:payments

┌──────────────┐  ┌────────────────┐  ┌──────────────┐
│  Delivery    │  │  Notification  │  │    Export    │
│ Service :8006│  │ Service :8007  │  │ Service :8008│
└──────┬───────┘  └───────┬────────┘  └──────┬───────┘
       │            stateless                │
  БД:delivery                           БД:exports
                                             │
                                      ┌──────▼───────┐
                                      │   Storage    │
                                      │ Service :8009│
                                      └──────┬───────┘
                                         S3 / Local
```

### Цільова топологія Docker Compose

```yaml
services:
  # BFF шар
  bff-mobile:    { порт: 8010 }
  bff-desktop:   { порт: 8011 }
  bff-public:    { порт: 8012 }

  # Основні сервіси
  user-service:         { порт: 8001, db: db-user }
  catalog-service:      { порт: 8002, db: db-catalog }
  cart-service:         { порт: 8003, db: db-cart }
  order-service:        { порт: 8004, db: db-order }
  payment-service:      { порт: 8005, db: db-payment }
  delivery-service:     { порт: 8006, db: db-delivery }
  notification-service: { порт: 8007, stateless: true }
  export-service:       { порт: 8008, db: db-export }
  export-worker:        { команда: messenger:consume async }
  storage-service:      { порт: 8009, stateless: true }

  # Інфраструктура
  rabbitmq:    { порт: 5672, ui: 15672 }
  db-user:     { postgres: 16 }
  db-catalog:  { postgres: 16 }
  db-cart:     { postgres: 16 }
  db-order:    { postgres: 16 }
  db-payment:  { postgres: 16 }
  db-delivery: { postgres: 16 }
  db-export:   { postgres: 16 }
  mailpit:     { smtp: 1025, ui: 8025 }
  jaeger:      { ui: 16686 }
```

---

## 3. Межі сервісів (DDD Bounded Contexts)

### Карта обмежених контекстів

```
┌──────────────────────────────────────────────────────────┐
│                   Контекст Користувача                    │
│  Постачальник ідентичності для всіх інших контекстів     │
│  Видає JWT токени; інші сервіси перевіряють публічний ключ│
└────────────────────────┬─────────────────────────────────┘
                         │ JWT claims (userId, roles)
     ┌───────────────────┼────────────────────┐
     ▼                   ▼                    ▼
┌──────────┐      ┌──────────┐        ┌──────────────┐
│ Catalog  │◄─────│   Cart   │        │    Order     │
│ Контекст │ HTTP │ Контекст │──────► │  Контекст    │
│          │      │          │знімок  │              │
└──────────┘      └──────────┘        └──────┬───────┘
                                             │ події
                                      ┌──────▼───────┐
                                      │   Payment    │
                                      │  Контекст    │
                                      └──────┬───────┘
                                             │ події
                                      ┌──────▼───────┐
                                      │   Delivery   │
                                      │  Контекст    │
                                      └──────────────┘

┌──────────────────────────────────────────────────────────┐
│  Контекст Сповіщень — підписується на всі події          │
│  Контекст Експорту — читає всі контексти через HTTP API  │
│  Контекст Зберігання — спільна інфраструктура            │
└──────────────────────────────────────────────────────────┘
```

### Правила ізоляції

1. Сервіс **ніколи** не імпортує клас Entity іншого сервісу
2. Сервіс **ніколи** не виконує JOIN через межі сервісів
3. Дані іншого сервісу отримуються виключно через **HTTP API або повідомлення подій**
4. Посилання між сервісами використовують лише **рядки UUID** — жодних FK constraints у БД
5. Коли сервісу потрібні дані з іншого домену, він зберігає **знімок** (денормалізовану копію)

### Інвентаризація (Inventory)

Управління запасами **свідомо залишено всередині Catalog Service**, а не виділено в окремий сервіс: рівень запасу (`products.stock`) і життєвий цикл продукту змінюються разом, тож розрив тут створив би більше міжсервісних викликів, ніж користі. Проте сам **облік резервувань ведеться окремою таблицею `stock_reservations`** (див. §4.2): без неї Checkout Saga не має чим тримати стан «зарезервовано, але не оплачено», уникати гонок і виконувати компенсацію (`StockReleased`). Якщо в майбутньому інвентар потребуватиме незалежного масштабування чи окремих складів — `stock` + `stock_reservations` виносяться в окремий Inventory Service без зміни контрактів подій.

---

## 4. Дизайн сервісів

---

### 4.1 User Service

**Відповідальності**: реєстрація, автентифікація, видача JWT, OAuth (Google/GitHub), профілі користувачів

**API Endpoints**

```
POST   /api/auth/register           Реєстрація через email + пароль
POST   /api/auth/login              Видача JWT access + refresh токенів
POST   /api/auth/refresh            Оновлення access токена через refresh токен
POST   /api/auth/logout             Відкликання refresh токена
GET    /api/auth/google             Перенаправлення на Google OAuth
GET    /api/auth/google/callback    Обробка Google OAuth callback
GET    /api/auth/github             Перенаправлення на GitHub OAuth
GET    /api/auth/github/callback    Обробка GitHub OAuth callback
GET    /api/users/{id}              Отримання профілю (ROLE_USER: лише власний)
PUT    /api/users/{id}              Оновлення профілю
GET    /api/users                   Список всіх користувачів (лише ROLE_ADMIN)
GET    /health/live                 Liveness probe
GET    /health/ready                Readiness probe (БД + RabbitMQ)
```

**Схема даних**

```sql
CREATE TABLE users (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email      VARCHAR(180) UNIQUE NOT NULL,
    password   VARCHAR(255),                          -- NULL для OAuth користувачів
    full_name  VARCHAR(255),
    roles      JSONB NOT NULL DEFAULT '["ROLE_USER"]',
    google_id  VARCHAR(255),
    github_id  VARCHAR(255),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX idx_users_email     ON users(email);
CREATE INDEX        idx_users_google_id ON users(google_id) WHERE google_id IS NOT NULL;
CREATE INDEX        idx_users_github_id ON users(github_id) WHERE github_id IS NOT NULL;
```

> **Код-гап.** Поточна сутність `User` (`src/User/Domain/Entity/User.php`) має лише поле `googleId`; поля `githubId` ще немає, хоча `GitHubAuthController` уже існує. Колонку `github_id` тут наведено як цільову — під час виокремлення сервісу її треба додати в модель і backfill для наявних GitHub-користувачів.

**Публіковані події**

| Подія | Payload | Підписники |
|---|---|---|
| `UserRegistered` | `{userId, email, fullName}` | Notification |
| `UserLoggedIn` | `{userId, email, sessionId}` | Cart (міграція), Notification |
| `UserUpdated` | `{userId, changedFields[]}` | Notification |

**Споживані події**: відсутні

**Зовнішні залежності**: Google OAuth API, GitHub OAuth API, RSA ключова пара (JWT RS256)

---

### 4.2 Catalog Service

**Відповідальності**: продукти, категорії, атрибути продуктів, управління зображеннями, резервування запасів

**API Endpoints**

```
GET    /api/products                Список продуктів (фільтри: category, price, stock, featured)
GET    /api/products/featured       Рекомендовані продукти для головної сторінки
GET    /api/products/{id}           Деталі продукту
POST   /api/products                Створення продукту (ROLE_ADMIN)
PUT    /api/products/{id}           Оновлення продукту (ROLE_ADMIN)
DELETE /api/products/{id}           Видалення продукту (ROLE_ADMIN)
POST   /api/products/{id}/presign   Отримання S3 presigned URL для завантаження (ROLE_ADMIN)
GET    /api/categories              Дерево категорій
GET    /api/categories/{slug}       Категорія з її продуктами
POST   /api/categories              Створення категорії (ROLE_ADMIN)
PUT    /api/categories/{id}         Оновлення категорії (ROLE_ADMIN)
DELETE /api/categories/{id}         Видалення категорії (ROLE_ADMIN)
GET    /health/live                 Liveness probe
GET    /health/ready                Readiness probe (БД + RabbitMQ + Storage)
```

**Схема даних**

```sql
CREATE TABLE categories (
    id        UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name      VARCHAR(255) NOT NULL,
    slug      VARCHAR(255) UNIQUE NOT NULL,
    parent_id UUID REFERENCES categories(id) ON DELETE SET NULL
);
CREATE INDEX idx_categories_parent ON categories(parent_id);
CREATE INDEX idx_categories_slug   ON categories(slug);

CREATE TABLE brands (
    id   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(255) NOT NULL
);

CREATE TABLE products (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id UUID REFERENCES categories(id) ON DELETE SET NULL,
    brand_id    UUID REFERENCES brands(id)     ON DELETE SET NULL,  -- nullable (Product.brand)
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    price       INTEGER NOT NULL,           -- зберігається в центах
    stock       INTEGER NOT NULL DEFAULT 0,
    image       VARCHAR(500),
    is_featured BOOLEAN NOT NULL DEFAULT FALSE,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_brand    ON products(brand_id);
CREATE INDEX idx_products_featured ON products(is_featured) WHERE is_featured = TRUE;
CREATE INDEX idx_products_stock    ON products(stock)        WHERE stock > 0;

CREATE TABLE printer_models (
    id       UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    brand_id UUID NOT NULL REFERENCES brands(id) ON DELETE CASCADE,
    name     VARCHAR(255) NOT NULL
);
CREATE INDEX idx_printer_models_brand ON printer_models(brand_id);

CREATE TABLE product_attributes (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    name       VARCHAR(255) NOT NULL,
    value      VARCHAR(500) NOT NULL
);
CREATE INDEX idx_product_attributes_product ON product_attributes(product_id);

-- Облік резервувань запасів для Checkout Saga: тримає стан "зарезервовано, але не оплачено".
-- Доступний запас = products.stock − SUM(stock_reservations.quantity WHERE status = 'HELD').
CREATE TYPE reservation_status AS ENUM ('HELD', 'COMMITTED', 'RELEASED');

CREATE TABLE stock_reservations (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    product_id UUID NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    order_id   UUID NOT NULL,                    -- UUID посилання на Order (без FK)
    quantity   INTEGER NOT NULL CHECK (quantity > 0),
    status     reservation_status NOT NULL DEFAULT 'HELD',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ                        -- TTL для авто-звільнення "завислих" резервів
);
CREATE INDEX idx_stock_reservations_product ON stock_reservations(product_id) WHERE status = 'HELD';
CREATE INDEX idx_stock_reservations_order   ON stock_reservations(order_id);
```

> **Семантика резервування.** На `OrderCreated` Catalog атомарно вставляє рядки `HELD` (за умови достатнього доступного запасу) → `StockReserved`; інакше → `StockReservationFailed`. На `OrderPaid` резерв переходить у `COMMITTED` і `products.stock` зменшується; на `OrderCancelled` — у `RELEASED` (`StockReleased`). Фоновий процес звільняє резерви з простроченим `expires_at`.

**Публіковані події**

| Подія | Payload | Підписники |
|---|---|---|
| `ProductCreated` | `{productId, name, price, stock, categoryId}` | — |
| `ProductUpdated` | `{productId, changedFields[]}` | Cart (оновлення ціни) |
| `ProductDeleted` | `{productId}` | Cart, Notification |
| `StockReserved` | `{productId, quantity, orderId}` | Order (крок саги 2→3) |
| `StockReservationFailed` | `{productId, quantity, orderId, reason}` | Order (компенсація саги) |
| `StockReleased` | `{productId, quantity, orderId}` | — |

**Споживані події**

| Подія | Дія |
|---|---|
| `OrderCreated` | Резервування запасів для всіх позицій замовлення |
| `OrderCancelled` | Звільнення зарезервованих запасів |

**Зовнішні залежності**: Storage Service (зображення продуктів)

---

### 4.3 Cart Service

**Відповідальності**: кошик покупок (гостьова сесія + авторизований БД), міграція кошика при вході

**API Endpoints**

```
GET    /api/cart                    Отримання поточного кошика (гість або авторизований)
POST   /api/cart/items              Додавання товару {productId, quantity}
PUT    /api/cart/items/{itemId}     Оновлення кількості {quantity}
DELETE /api/cart/items/{itemId}     Видалення позиції
DELETE /api/cart                    Очищення кошика
POST   /api/cart/migrate            Міграція гостьового кошика → користувача (внутрішній)
GET    /api/cart/summary            Знімок для Order Service під час оформлення замовлення
GET    /health/live                 Liveness probe
GET    /health/ready                Readiness probe (БД + RabbitMQ + Catalog доступний)
```

**Схема даних**

```sql
CREATE TABLE carts (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID,                          -- NULL для гостьових кошиків
    session_id VARCHAR(128),                  -- NULL для авторизованих кошиків
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT uq_cart_user    UNIQUE (user_id),
    CONSTRAINT uq_cart_session UNIQUE (session_id),
    CONSTRAINT chk_cart_owner  CHECK (
        (user_id IS NOT NULL AND session_id IS NULL) OR
        (user_id IS NULL    AND session_id IS NOT NULL)
    )
);

CREATE TABLE cart_items (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    cart_id      UUID NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
    product_id   UUID NOT NULL,                 -- UUID посилання, без FK (міжсервісний)
    product_name VARCHAR(255) NOT NULL,         -- знімок з Catalog Service
    price        INTEGER NOT NULL,              -- знімок у центах
    quantity     INTEGER NOT NULL CHECK (quantity > 0)
);
CREATE INDEX idx_cart_items_cart    ON cart_items(cart_id);
CREATE INDEX idx_cart_items_product ON cart_items(product_id);
```

> **Зміна поведінки.** Зараз гостьовий кошик зберігається у **PHP-сесії** (`CartService`, ключ `cart`), а не в БД. Цільовий дизайн робить гостьовий кошик **рядком у таблиці `carts`** з `session_id` — це дозволяє Cart Service бути stateless щодо застосунку й переживати рестарти, але є свідомою зміною поточної моделі зберігання.

**Публіковані події**

| Подія | Payload | Підписники |
|---|---|---|
| `CartCleared` | `{cartId, userId}` | — |
| `CartMigrated` | `{sessionId, userId, cartId}` | — |

**Споживані події**

| Подія | Дія |
|---|---|
| `UserLoggedIn` | Запуск міграції сесійного кошика → кошик користувача |
| `ProductUpdated` | Оновлення знімку ціни при зміні ціни продукту |

**Зовнішні залежності**: Catalog Service HTTP (`GET /api/products/{id}` — валідація продукту та отримання актуальної ціни при додаванні в кошик)

---

### 4.4 Order Service

**Відповідальності**: створення замовлень, життєвий цикл статусів, оркестрація оформлення через Сагу

**API Endpoints**

```
POST   /api/orders                  Створення замовлення (запускає Checkout Saga)
GET    /api/orders                  Список замовлень поточного користувача
GET    /api/orders/{id}             Деталі замовлення
PUT    /api/orders/{id}/status      Зміна статусу (ROLE_ADMIN)
GET    /api/orders/admin            Всі замовлення з фільтрами (ROLE_ADMIN)
GET    /health/live                 Liveness probe
GET    /health/ready                Readiness probe (БД + RabbitMQ)
```

**POST /api/orders — Тіло запиту**

```json
{
  "shippingAddress": {
    "street": "вул. Хрещатик 1",
    "city": "Київ",
    "zip": "01001",
    "country": "UA"
  }
}
```

Позиції кошика отримуються з Cart Service (`GET /api/cart/summary`) під час оформлення.

**Схема даних**

```sql
-- Набір відповідає фактичним статусам у коді (OrderCrudController, StripeWebhookController):
CREATE TYPE order_status AS ENUM (
    'PENDING', 'PAID', 'FAILED',
    'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'
);

CREATE TABLE orders (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id          UUID NOT NULL,              -- UUID посилання, без FK (міжсервісний)
    user_email       VARCHAR(180) NOT NULL,      -- знімок
    status           order_status NOT NULL DEFAULT 'PENDING',
    total_amount     INTEGER NOT NULL,           -- центи
    shipping_address JSONB NOT NULL,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_orders_status  ON orders(status);
CREATE INDEX idx_orders_created ON orders(created_at DESC);

CREATE TABLE order_items (
    id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id     UUID NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id   UUID NOT NULL,                 -- UUID посилання, без FK (міжсервісний)
    product_name VARCHAR(255) NOT NULL,         -- знімок
    quantity     INTEGER NOT NULL CHECK (quantity > 0),
    price        INTEGER NOT NULL               -- знімок у центах на момент замовлення
);
CREATE INDEX idx_order_items_order   ON order_items(order_id);
CREATE INDEX idx_order_items_product ON order_items(product_id);
```

**Checkout Saga (Хореографія)**

> **Узгоджено з реальною інтеграцією Stripe.** Оплата інтерактивна: Payment створює **Stripe Checkout Session** і повертає `checkoutUrl`, клієнт редиректиться на hosted-сторінку Stripe, а підтвердження приходить **webhook'ом** — це не headless-списання за подією. Order лишається в `PENDING`, поки не надійде webhook. Термінальні статуси: `PAID` (успіх) або `FAILED` (відмова оплати); `CANCELLED` — для невдалого резервування / дії адміна.

```
Крок 1  OrderService     Створює Order (PENDING)
        Публікує ───────► OrderCreated {orderId, userId, userEmail, items[], totalAmount, shippingAddress}

Крок 2  CatalogService   Слухає OrderCreated
        Резервує запаси (вставляє stock_reservations = HELD)
        Публікує ───────► StockReserved {orderId, items[]}
                    АБО ── StockReservationFailed {orderId, productId, requested, available, reason}

Крок 3а OrderService     Слухає StockReserved
        Order лишається PENDING (очікує оплату)
        Публікує ───────► PaymentRequested {orderId, paymentId, amount, currency, userId, idempotencyKey}

Крок 3б OrderService     Слухає StockReservationFailed
        Order → CANCELLED (резерв не утримувався — компенсація не потрібна)
        Публікує ───────► OrderCancelled {orderId, userId, reason: 'out_of_stock', items[]}

Крок 4  PaymentService   Слухає PaymentRequested
        Створює Stripe Checkout Session (mode=payment, metadata.order_id)
        Повертає checkoutUrl → клієнт редиректиться на hosted-сторінку Stripe

Крок 5  PaymentService   Отримує Stripe webhook (підпис + idempotency_key)
        checkout.session.completed ─► PaymentSucceeded {paymentId, orderId, amount, currency, providerTxId}
        expired / async_payment_failed ─► PaymentFailed {paymentId, orderId, reason, providerCode}

Крок 6а OrderService     Слухає PaymentSucceeded
        Order → PAID
        Публікує ───────► OrderPaid {orderId, userId, paidAt}

Крок 6б OrderService     Слухає PaymentFailed
        Order → FAILED
        Публікує ───────► OrderCancelled {orderId, userId, reason: 'payment_failed', items[]}
        * Термінальний статус замовлення = FAILED; подія OrderCancelled — це сигнал компенсації
          для звільнення запасів (її ім'я не змінює статус Order).

Крок 7а CatalogService   Слухає OrderPaid
        Резерв → COMMITTED, products.stock зменшується

Крок 7б CatalogService   Слухає OrderCancelled
        Резерв → RELEASED (компенсація) ──► StockReleased {orderId, items[]}
```

**Публіковані події**

| Подія | Payload |
|---|---|
| `OrderCreated` | `{orderId, userId, userEmail, items[], totalAmount, shippingAddress}` |
| `PaymentRequested` | `{orderId, paymentId, amount, currency, userId, idempotencyKey}` |
| `OrderPaid` | `{orderId, userId, paidAt}` |
| `OrderCancelled` | `{orderId, userId, reason, items[]}` |
| `OrderStatusChanged` | `{orderId, previousStatus, newStatus, changedAt}` |

**Споживані події**

| Подія | Дія |
|---|---|
| `StockReserved` | Просування саги: Order лишається PENDING, публікує `PaymentRequested` |
| `StockReservationFailed` | Компенсація: Order → CANCELLED (`reason: out_of_stock`) |
| `PaymentSucceeded` | Просування саги: Order → PAID |
| `PaymentFailed` | Компенсація: Order → FAILED, публікує `OrderCancelled` для звільнення запасів |

---

### 4.5 Payment Service

**Відповідальності**: платіжні транзакції, webhook від провайдерів, повернення коштів

**API Endpoints**

```
POST   /api/payments                Ініціація оплати (внутрішній, з Order Saga)
GET    /api/payments/{id}           Статус оплати
POST   /api/payments/{id}/refund    Повернення коштів (ROLE_ADMIN)
POST   /api/webhooks/stripe         Приймання Stripe webhook (підписаний)
POST   /api/webhooks/liqpay         Приймання LiqPay webhook (підписаний HMAC)
GET    /health/live                 Liveness probe
GET    /health/ready                Readiness probe (БД + RabbitMQ + Stripe API)
```

**Схема даних**

```sql
CREATE TYPE payment_status   AS ENUM ('PENDING','PROCESSING','SUCCEEDED','FAILED','REFUNDED');
CREATE TYPE payment_provider AS ENUM ('stripe','paypal','liqpay');

CREATE TABLE payments (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID NOT NULL UNIQUE,
    amount          INTEGER NOT NULL,               -- центи
    currency        CHAR(3) NOT NULL DEFAULT 'EUR',
    status          payment_status NOT NULL DEFAULT 'PENDING',
    provider        payment_provider,
    provider_tx_id  VARCHAR(255),
    idempotency_key VARCHAR(255) UNIQUE NOT NULL,   -- захист від дублювання webhook
    metadata        JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_payments_status   ON payments(status);

CREATE TABLE refunds (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    payment_id      UUID NOT NULL REFERENCES payments(id),
    amount          INTEGER NOT NULL,
    reason          TEXT,
    status          VARCHAR(50),
    provider_ref_id VARCHAR(255),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

**Публіковані події**

| Подія | Payload |
|---|---|
| `PaymentSucceeded` | `{paymentId, orderId, amount, currency, providerTxId}` |
| `PaymentFailed` | `{paymentId, orderId, reason, providerCode}` |
| `RefundProcessed` | `{refundId, paymentId, orderId, amount}` |

**Споживані події**

| Подія | Дія |
|---|---|
| `PaymentRequested` | Створити Stripe Checkout Session (`mode=payment`, `metadata.order_id`) і повернути `checkoutUrl` для редиректу клієнта |

**Потік оплати (Stripe Checkout, redirect-модель):**

```
PaymentRequested → створення Checkout Session → повернення checkoutUrl
   → редирект клієнта на hosted-сторінку Stripe → оплата на боці Stripe
   → webhook (checkout.session.completed | expired) → PaymentSucceeded | PaymentFailed
```

Списання **не** ініціюється headless-подією — підтвердження приходить лише webhook'ом. Це відповідає наявному `StripeCheckoutService` + `StripeWebhookController` у моноліті.

**Ключовий патерн**: **Idempotency Key** — кожен webhook обробляється рівно один раз завдяки унікальному обмеженню `idempotency_key`.

**Зовнішні залежності**: Stripe API, LiqPay API

---

### 4.6 Delivery Service

**Відповідальності**: створення відправлень, відстеження, інтеграція з логістичними провайдерами

**API Endpoints**

```
POST   /api/shipments                    Створення відправлення (внутрішній)
GET    /api/shipments/{id}               Статус відправлення
GET    /api/shipments/order/{orderId}    Відправлення для замовлення
GET    /api/shipments/{id}/tracking      Історія подій відстеження
POST   /api/webhooks/novaposhta          Webhook від Нової Пошти
GET    /health/live                      Liveness probe
GET    /health/ready                     Readiness probe (БД + RabbitMQ + API Нової Пошти)
```

**Схема даних**

```sql
CREATE TYPE shipment_status AS ENUM (
    'PENDING','PICKED_UP','IN_TRANSIT','OUT_FOR_DELIVERY','DELIVERED','FAILED'
);

CREATE TABLE shipments (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    order_id        UUID NOT NULL UNIQUE,
    provider        VARCHAR(50) NOT NULL,    -- 'nova_poshta', 'ukrposhta', 'dhl'
    tracking_number VARCHAR(100),
    status          shipment_status NOT NULL DEFAULT 'PENDING',
    address         JSONB NOT NULL,
    estimated_at    DATE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Event Sourcing: незмінний лог відстеження, ніколи не оновлюється
CREATE TABLE tracking_events (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    shipment_id UUID NOT NULL REFERENCES shipments(id),
    status      VARCHAR(100) NOT NULL,
    location    VARCHAR(255),
    description TEXT,
    occurred_at TIMESTAMPTZ NOT NULL,
    recorded_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_tracking_shipment ON tracking_events(shipment_id, occurred_at DESC);
```

**Публіковані події**

| Подія | Payload |
|---|---|
| `ShipmentCreated` | `{shipmentId, orderId, trackingNumber, provider}` |
| `TrackingUpdated` | `{shipmentId, orderId, status, location}` |
| `ShipmentDelivered` | `{shipmentId, orderId, deliveredAt}` |

**Споживані події**

| Подія | Дія |
|---|---|
| `OrderPaid` | Створення відправлення для замовлення |

**Ключовий патерн**: **Event Sourcing** для `tracking_events` — незмінний лог тільки для додавання. Історія статусів ніколи не перезаписується.

**Зовнішні залежності**: API Нової Пошти, API Укрпошти, API DHL (Polling Scheduler для провайдерів без webhook)

---

### 4.7 Notification Service

**Відповідальності**: централізована відправка email, SMS та push-сповіщень

**API**: Відсутній публічний HTTP API — працює виключно як споживач подій.

**Канали доставки**

| Канал | Провайдер (dev) | Провайдер (prod) |
|---|---|---|
| Email | Mailpit (SMTP 1025) | AWS SES / SendGrid |
| SMS | Лише логування | Twilio / Nexmo |
| Push (iOS) | Лише логування | APNs |
| Push (Android) | Лише логування | FCM |

**Споживані події → Ініційоване сповіщення**

| Подія | Сповіщення |
|---|---|
| `UserRegistered` | Привітальний email |
| `OrderCreated` | Email підтвердження замовлення |
| `PaymentSucceeded` | Email квитанція оплати |
| `PaymentFailed` | Сповіщення про проблему (email + SMS) |
| `ShipmentCreated` | Номер відстеження (email + SMS) |
| `TrackingUpdated` | Оновлення статусу (push-сповіщення) |
| `ShipmentDelivered` | Підтвердження доставки (email) |
| `ExportJobCompleted` | Посилання для завантаження (email) |
| `ExportJobFailed` | Повідомлення про помилку (email) |

**Stateless**: Немає власної бази даних. Споживає події та відправляє сповіщення.

---

### 4.8 Export Service

**Відповідальності**: управління асинхронними завданнями експорту, витягування даних, генерація файлів (CSV/JSON/XML)

**API Endpoints**

```
POST   /api/exports                 Створення завдання {type, format, filters}
GET    /api/exports                 Список завдань (ROLE_ADMIN)
GET    /api/exports/{id}            Статус та метадані завдання
GET    /api/exports/{id}/download   Завантаження готового файлу
GET    /health/live                 Liveness probe
GET    /health/ready                Readiness probe (БД + RabbitMQ + Storage)
```

**Схема даних**

```sql
CREATE TYPE export_type   AS ENUM ('products','orders','users');
CREATE TYPE export_format AS ENUM ('csv','json','xml');
CREATE TYPE export_status AS ENUM ('pending','processing','completed','failed');

CREATE TABLE export_jobs (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type          export_type NOT NULL,
    format        export_format NOT NULL,
    status        export_status NOT NULL DEFAULT 'pending',
    file_path     VARCHAR(500),
    filters       JSONB,
    requested_by  VARCHAR(180) NOT NULL,  -- email користувача (м'яке посилання, без FK)
    error_message TEXT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at  TIMESTAMPTZ
);
CREATE INDEX idx_export_jobs_status  ON export_jobs(status);
CREATE INDEX idx_export_jobs_created ON export_jobs(created_at DESC);
```

**Процес асинхронної обробки**

```
POST /api/exports
  → Зберегти ExportJob (статус: pending)
  → Відправити ProcessExportMessage в RabbitMQ

[Воркер] ProcessExportHandler отримує повідомлення
  → Позначити: processing
  → Посторінковий HTTP GET до відповідного сервісу:
      products → GET /api/products?page=1&limit=500&{filters}
      orders   → GET /api/orders/admin?page=1&limit=500&{filters}
      users    → GET /api/users?page=1&limit=500&{filters}
  → Форматувати дані (CsvFormatter / JsonFormatter / XmlFormatter)
  → PUT файл в Storage Service: /api/storage/exports/{jobId}.{ext}
  → Позначити: completed, зберегти file_path
  → Опублікувати ExportJobCompleted

При помилці:
  → Позначити: failed, зберегти error_message
  → Опублікувати ExportJobFailed
```

**Публіковані події**

| Подія | Payload |
|---|---|
| `ExportJobCompleted` | `{jobId, filePath, requestedBy}` |
| `ExportJobFailed` | `{jobId, errorMessage, requestedBy}` |

**Зовнішні залежності**: Catalog Service API, Order Service API, User Service API, Storage Service

---

### 4.9 Storage Service

**Відповідальності**: завантаження/отримання файлів, генерація S3 presigned URLs, підтримка локальної файлової системи

**API Endpoints**

```
PUT    /api/storage/{key}           Завантаження файлу (multipart або raw body)
GET    /api/storage/{key}           Отримання файлу або перенаправлення на S3
DELETE /api/storage/{key}           Видалення файлу
POST   /api/storage/presign         Presigned S3 PUT URL для прямого завантаження з браузера
GET    /health/live                 Liveness probe
GET    /health/ready                Перевірка з'єднання з S3
```

**Простори імен ключів**

| Простір | Використовується | Приклад |
|---|---|---|
| `products/{id}-{filename}` | Catalog Service | `products/uuid-iphone.jpg` |
| `exports/{type}/{format}/{jobId}.{ext}` | Export Service | `exports/orders/csv/uuid.csv` |

**Stateless**: Немає власної бази даних. Обгортає S3 SDK (або локальну файлову систему) за єдиним HTTP API.

**Зовнішні залежності**: AWS S3 (або локальна файлова система через змінну `STORAGE_TYPE`)

---

## 5. Міжсервісна комунікація

### Синхронні HTTP виклики

| Ініціатор | Отримувач | Endpoint | Коли |
|---|---|---|---|
| Cart Service | Catalog Service | `GET /api/products/{id}` | Додавання товару в кошик (валідація + ціна) |
| Order Service | Cart Service | `GET /api/cart/summary` | Оформлення: отримання знімку кошика |
| Export Service | Catalog Service | `GET /api/products` (посторінково) | Генерація експорту |
| Export Service | Order Service | `GET /api/orders/admin` (посторінково) | Генерація експорту |
| Export Service | User Service | `GET /api/users` (посторінково) | Генерація експорту |
| Будь-який сервіс | Storage Service | `PUT/GET /api/storage/{key}` | Завантаження/отримання файлів |

### Асинхронні події (RabbitMQ)

RabbitMQ використовує **topic exchanges** для кожного домену: `user.events`, `catalog.events`, `cart.events`, `order.events`, `payment.events`, `delivery.events`, `export.events`

Повний каталог подій зі схемами payload дивіться у [event-catalog.md](./event-catalog.md).

### Transactional Outbox

Щоб гарантувати, що подія публікується **тоді й лише тоді**, коли зафіксовано зміну стану (немає втрати чи «фантомних» подій при збої між commit і publish), кожен сервіс-продюсер (Order, Payment, Catalog) пише подію у локальну таблицю `outbox` **в тій самій транзакції БД**, що й зміну стану. Окремий relay-процес читає незабрані рядки й публікує їх у RabbitMQ, позначаючи `published_at`.

```sql
CREATE TABLE outbox (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    aggregate     VARCHAR(100) NOT NULL,   -- 'order', 'payment', 'product'
    event_name    VARCHAR(100) NOT NULL,   -- 'OrderCreated', ...
    payload       JSONB NOT NULL,
    trace_id      VARCHAR(64),
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    published_at  TIMESTAMPTZ              -- NULL = ще не опубліковано
);
CREATE INDEX idx_outbox_unpublished ON outbox(created_at) WHERE published_at IS NULL;
```

Relay реалізується або polling-воркером, або через Debezium CDC. Споживачі мають бути **ідемпотентними** (дедуплікація за `eventId`), бо relay гарантує доставку *at-least-once*.

### Автоматичний вимикач (Circuit Breaker)

Застосовується до всіх синхронних HTTP викликів, де відмова залежного сервісу не повинна поширюватися:

| Виклик | Поведінка при відмові |
|---|---|
| Cart → Catalog (додавання товару) | Повернути помилку: "Товар недоступний" |
| Order → Cart (оформлення) | Повернути помилку: "Кошик недоступний, спробуйте ще раз" |
| Export → будь-який сервіс | Позначити завдання як failed з описом помилки |

> **Механізм.** У Symfony немає вбудованого circuit breaker. Рекомендований підхід: бібліотека рівня застосунку (напр. `ackintosh/ganesha`, інтегрована з `HttpClientInterface`) для retry/timeout/half-open станів; на платформенному рівні — service mesh (Istio/Linkerd), що дає circuit breaking, retry і timeouts без коду застосунку.

---

## 6. BFF Шар

Кожен BFF є **тонким агрегаційним шаром**, відповідальним за: форматування специфічне для клієнта, тип автентифікації та агрегацію відповідей. Бізнес-логіка залишається в мікросервісах.

### BFF Mobile (порт 8010)

- Стиснутий payload (URL мініатюри, мінімальні поля)
- Агрегація Product + Category в одній відповіді
- Анонімний кошик через сесійний cookie
- Реєстрація токена push-сповіщень
- Оптимізовано для поганого з'єднання (`ETag`, `Cache-Control`)

### BFF Desktop / Admin (порт 8011)

- Повні вкладені об'єкти з пов'язаними даними
- Маршрутизація EasyAdmin CRUD дій до відповідних сервісів
- Автентифікація через сесійний cookie + пересилання JWT до сервісів
- UI експорту проксіюється до Export Service

### BFF Public API (порт 8012)

- Версіонований REST: `/v1/`, `/v2/`
- Автентифікація через API Key (додатково до JWT)
- Суворе обмеження швидкості (нижче ніж для внутрішніх клієнтів)
- Документація OpenAPI 3.0 за адресою `/v1/docs`

---

## 7. Health Check API

Кожен сервіс надає два стандартних endpoint для Kubernetes проб.

### Endpoints

```
GET /health/live   → 200 якщо процес живий (liveness probe)
GET /health/ready  → 200 якщо сервіс готовий приймати трафік (readiness probe)
```

### Формат відповіді

```json
// 200 OK — справний
{
  "status": "ok",
  "checks": {
    "database":  { "status": "ok",    "latency_ms": 2 },
    "rabbitmq":  { "status": "ok",    "latency_ms": 1 },
    "storage":   { "status": "ok",    "latency_ms": 12 }
  }
}

// 503 Service Unavailable — несправний
{
  "status": "error",
  "checks": {
    "database":  { "status": "error", "detail": "Connection refused" },
    "rabbitmq":  { "status": "ok",    "latency_ms": 1 }
  }
}
```

### Матриця перевірок Readiness

| Сервіс | БД | RabbitMQ | Зовнішні |
|---|---|---|---|
| User Service | ✅ | ✅ | — |
| Catalog Service | ✅ | ✅ | Storage Service `/health/live` |
| Cart Service | ✅ | ✅ | Catalog Service `/health/live` |
| Order Service | ✅ | ✅ | — |
| Payment Service | ✅ | ✅ | Доступність Stripe API |
| Delivery Service | ✅ | ✅ | Доступність API Нової Пошти |
| Notification Service | — | ✅ | Доступність SMTP |
| Export Service | ✅ | ✅ | Storage Service `/health/live` |
| Storage Service | — | — | S3 `HeadBucket` |

### Інтеграція з Docker Compose

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/health/live"]
  interval: 10s
  timeout: 5s
  retries: 3
  start_period: 30s

depends_on:
  catalog-service:
    condition: service_healthy
```

### Реалізація на Symfony

```php
#[Route('/health/live', methods: ['GET'])]
public function live(): JsonResponse
{
    return new JsonResponse(['status' => 'ok']);
}

#[Route('/health/ready', methods: ['GET'])]
public function ready(Connection $db): JsonResponse
{
    $checks = [];
    try {
        $start = microtime(true);
        $db->executeQuery('SELECT 1');
        $checks['database'] = [
            'status'     => 'ok',
            'latency_ms' => (int) round((microtime(true) - $start) * 1000),
        ];
    } catch (\Throwable $e) {
        $checks['database'] = ['status' => 'error', 'detail' => $e->getMessage()];
        return new JsonResponse(['status' => 'error', 'checks' => $checks], 503);
    }
    return new JsonResponse(['status' => 'ok', 'checks' => $checks]);
}
```

---

## 8. Спостережуваність

| Компонент | Інструмент | Призначення |
|---|---|---|
| **Розподілене трасування** | Jaeger | Трасування запитів через всі сервіси через заголовок `X-Trace-Id` |
| **Централізоване логування** | Loki + Grafana | JSON структуровані логи агреговані з усіх сервісів |
| **Метрики** | Prometheus + Grafana | RPS, p99 затримка, глибина черги, рівень помилок на сервіс |
| **Health Checks** | `/health/live` + `/health/ready` | Kubernetes liveness/readiness проби |
| **Сповіщення** | Grafana Alerts | Тригери на: платіжні помилки > 5%, глибина черги > 1000, сервіс недоступний |

### Поширення трасування

Кожен вхідний HTTP запит отримує заголовок `X-Trace-Id` (генерується BFF якщо відсутній). Кожен сервіс передає цей заголовок у всіх вихідних HTTP викликах та включає його до заголовків повідомлень RabbitMQ.

---

## 9. Безпека

| Механізм | Де застосовується |
|---|---|
| **JWT RS256** | User Service видає токени; BFF перевіряє підпис; мікросервіси витягують `userId` та `roles` з перевірених claims |
| **Сервіс-до-сервісні токени** | Внутрішні API виклики (Export → Catalog тощо) використовують окремі короткострокові сервісні токени, не JWT користувача |
| **mTLS** | Між сервісами у production (Kubernetes + Istio) |
| **Мережева ізоляція** | Payment Service недоступний з публічного інтернету; доступний лише через RabbitMQ події та внутрішню мережу |
| **Перевірка підпису webhook** | Stripe: заголовок `Stripe-Signature`; LiqPay: HMAC-SHA512 |
| **Патерн Outbox** | Гарантує доставку подій в рамках тієї ж DB транзакції що й зміна стану (схема таблиці та relay — див. §5 «Transactional Outbox») |
| **Обмеження швидкості** | Застосовується на рівні BFF шару для кожного типу клієнта (суворіше для Public API) |

---

## 10. Стратегія міграції

Міграція виконується за патерном **Strangler Fig**: поступове виокремлення сервісів поки моноліт продовжує працювати. Кожна фаза незалежно розгортається та тестується.

### Фаза 1 — Ізоляція модулів (в межах монолита, без нової інфраструктури)

Ціль: Видалити всі міждоменні зв'язності з існуючої кодової бази.

- [ ] Замінити міждоменні Doctrine `ManyToOne` зв'язки на поля UUID рядків
- [ ] Додати поле `product_name` до `order_items` та `cart_items`
- [ ] Замінити синхронний виклик `CartService` в `LoginListener` на відправку події `UserLoggedIn`
- [ ] Замінити прямі виклики Doctrine репозиторіїв у `ProcessExportHandler` на HTTP клієнти
- [ ] Перевірка: `grep -r "use App\\Catalog" src/Order/ src/Cart/` повертає нуль результатів

### Фаза 2 — База даних для кожного сервісу (розділення схем)

Ціль: Розділити єдину PostgreSQL на окремі схеми для кожного сервісу.

- [ ] Створити окремі схеми: `users`, `catalog`, `cart`, `orders`, `payments`, `delivery`, `exports`
- [ ] Видалити всі FK constraints між схемами
- [ ] Додати RabbitMQ до `docker-compose.yml`, замінити DSN транспорту `doctrine://`

**Підкрок 2.1 — Міграція первинних ключів `serial int` → `UUID` ⚠️ високий ризик.**
Усі сутності зараз мають int auto-increment PK (`#[ORM\GeneratedValue]`), а цільова модель вимагає UUID (правило «посилання лише UUID»). Це найризикованіша частина — виконувати поетапно, без простою:

- [ ] Додати нові колонки `uuid` (`gen_random_uuid()`) поряд з наявними `id` у кожній таблиці
- [ ] Backfill UUID для всіх рядків; додати unique-індекси
- [ ] Додати паралельні `*_uuid` колонки на всіх посиланнях (FK та майбутніх крос-сервісних) і backfill через JOIN за старими int
- [ ] Перемкнути застосунок/мапінг на читання-запис UUID; зберігати int тимчасово для звірки
- [ ] Після верифікації — зробити UUID первинним ключем, видалити старі int-колонки та int-FK
- [ ] Зовнішні ідентифікатори (URL, Stripe `metadata.order_id`) мають бути перевипущені/зворотно сумісні на час переходу

### Фаза 3 — Виокремлення User Service

Ціль: Перший самостійний мікросервіс — найменша зв'язність.

- [x] Новий Symfony додаток для User Service — `services/user-service/` (FrankenPHP, Symfony 7.4),
      власна БД `db-user`, порт 8001; register/login/JWT, `/api/users`, `/health/*`; 14 функц. тестів
- [x] Моноліт довіряє токенам сервісу — спільний RS256 keypair (підпис валідується монолітним public key:
      `openssl ... Verified OK`), `username`-claim резолвиться через провайдер моноліту. Моноліт незмінний.
- [ ] API Gateway маршрутизує `/api/auth/*` → User Service — **відкладено** (сервіс доступний напряму :8001)
- [ ] Моноліт читає `userId` + `roles` лише з JWT claims (повний cutover — коли приберемо таблицю `users`
      з моноліту; зараз перехідний стан зі знімком користувачів в обох)

> **Обсяг MVP (Strangler крок 1):** сервіс додано адитивно; web/admin-сесії та OAuth поки в моноліті.
> Поза обсягом цієї фази: API Gateway, перенесення OAuth, прод-розгортання сервісу (compose.prod + CI + секрети).

### Фаза 4 — Виокремлення Catalog Service

Ціль: Основний домен читання, дозволяє Cart та Order відв'язатися від даних продуктів.

- [x] Новий Symfony додаток для Catalog Service — `services/catalog-service/` (FrankenPHP, Symfony 7.4),
      власна БД `db-catalog`, порт 8002; read-API `GET /api/products` (фільтри+пагінація), `/products/{id}`,
      `/categories`, `/health/*`; service-to-service JWT (спільний keypair, лише верифікація); 8 функц. тестів
- [x] Перший споживач на HTTP — монолітний Export `ProductExtractor` читає продукти з сервісу через
      `CatalogProductClient` (HttpClient + сервісний JWT, посторінково), а не з локальних Doctrine-таблиць
- [ ] Cart та Order отримують дані продуктів через HTTP — **відкладено** (hot-path вітрини/кошика лишається
      на моноліті в цьому MVP)
- [ ] Storage Service виокремлений або вбудований — **відкладено**

> **Обсяг MVP (Strangler крок 2):** сервіс + read-API + один показовий споживач (Export). Вітрина, рендер
> кошика й адмінка поки читають каталог із моноліту. Поза обсягом: `stock_reservations` (Фаза 5),
> PrinterModel/атрибути, write-API/адмінка на сервісі, API Gateway, прод-розгортання.

### Фаза 4.5 — Catalog cutover (завершено): Catalog Service — єдине джерело правди

Ціль: завершити винесення Catalog — і читання, і запис каталогу в сервісі; усунути розбіжність даних.

- [x] Вітрина + рендер кошика моноліту читають каталог із Catalog Service (`CatalogClient` + view-DTO);
      `CartService`/`CheckoutController` оперують catalog-UUID (розблокувало справжню Checkout Saga)
- [x] Write-API сервісу (`POST/PUT/DELETE` products/categories/brands) під `ROLE_CATALOG_ADMIN` (RBAC на
      рівні firewall за HTTP-методом); адмінка моноліту пише через HTTP (`CatalogAdminClient` + кастомні
      сторінки `/admin/catalog/*` замість EasyAdmin-CRUD)
- [x] Каталог-таблиці моноліту (`products`/`categories`/`brands`/`product_attributes`) **дропнуто**
      (`Version20260619120000`); PrinterModel/printer-finder лишився в моноліті зі знімками
      `brand_slug`/`brand_name` (без FK на brands)
- [x] **Розбіжність даних усунено**: адмін-запис і storefront-читання йдуть в один сервіс (доведено E2E:
      адмін створює продукт → одразу видно на вітрині)

### Фаза 5 — Виокремлення Cart + Order Services + Checkout Saga

Ціль: Повний процес оформлення замовлення як розподілена транзакція.

- [x] **Order Service** виокремлено (orders + checkout Saga + Stripe), live у проді.
- [x] **Cart Service** виокремлено (крок 4): персистентний кошик залогінених (`db-cart`, :8004,
      API `GET/POST/PATCH/DELETE /api/carts/{userId}`), моноліт пише через `CartClient` (S2S JWT).
      Гостьовий кошик лишився в сесії моноліту (MVP); анонімний cookie-кошик — відкладено.
- [x] Хореографічна Сага (OrderCreated/OrderPaid/OrderCancelled → catalog-service `stock_reservations`).
- [ ] Двостороння компенсація (StockReserved/Failed назад в Order) — поза MVP (синхронний Stripe-redirect).

### Фаза 6 — Виокремлення Payment + Delivery Services

Ціль: Фінансовий та логістичний домени ізольовані.

- [ ] Обробники Stripe/LiqPay webhook у Payment Service
- [ ] Інтеграція Нової Пошти у Delivery Service
- [ ] Event Sourcing для `tracking_events` (append-only)

### Фаза 7 — Виокремлення Export + Notification + Storage Services

Ціль: Допоміжні сервіси повністю незалежні.

- [ ] Export Service читає дані через посторінкові внутрішні API
- [ ] Notification Service централізує всі email/SMS/push
- [ ] Storage Service як окремий проксі для S3/local

---

## Пов'язані документи

- [event-catalog.md](./event-catalog.md) — Повний каталог подій зі схемами payload
- [STORAGE_SETUP.md](./STORAGE_SETUP.md) — Налаштування S3 та локального сховища

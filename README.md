# PrintZone — інтернет-магазин витратних матеріалів для друку (Task-24)

Symfony-додаток електронної комерції (друкарський магазин: картриджі, тонери, drum units, стрічки) з чистою архітектурою, принципами SOLID та сучасними найкращими практиками.
Включає **REST API** на базі API Platform із захистом через **JWT-токени**, а також **OAuth 2.0 аутентифікацію** через Google та GitHub.

**Task 24** додає **аналіз та планування мікросервісної архітектури**: визначення меж сервісів за DDD, проектування API контрактів, схем даних, патернів комунікації та стратегії міграції від монолита до мікросервісів.

---

## Task 24: Мікросервісна архітектура

### Що зроблено

Проведено повний аналіз поточного монолита та спроектовано цільову мікросервісну архітектуру.

### Ідентифіковані мікросервіси

| Сервіс | Відповідальності | БД |
|---|---|---|
| **User Service** | Реєстрація, автентифікація, JWT, OAuth | `db-users` |
| **Catalog Service** | Продукти, категорії, атрибути, зображення | `db-catalog` |
| **Cart Service** | Кошик (гість + авторизований), міграція | `db-cart` |
| **Order Service** | Замовлення, lifecycle статусів, Checkout Saga | `db-orders` |
| **Payment Service** | Оплата, webhook Stripe/LiqPay, повернення | `db-payments` |
| **Delivery Service** | Відправлення, трекінг, Нова Пошта/DHL | `db-delivery` |
| **Notification Service** | Email, SMS, push-сповіщення (stateless) | — |
| **Export Service** | Async CSV/JSON/XML export jobs | `db-exports` |
| **Storage Service** | Файли S3/local (stateless proxy) | — |

> **User, Catalog, Cart, Order, Payment, Export, Storage** витягуються з наявних модулів коду. **Delivery** і **Notification** — нові (greenfield) сервіси: у поточному моноліті немає окремих доменів доставки чи сповіщень (сповіщення зараз надсилаються інлайн через Symfony Mailer).

### Застосовані патерни

| Патерн | Де застосовується |
|---|---|
| **Strangler Fig** | Поступова міграція монолита через API Gateway |
| **Database per Service** | Окрема PostgreSQL на кожен сервіс |
| **Choreography Saga** | Checkout: резервування → оплата → доставка |
| **Outbox Pattern** | Гарантована доставка подій з DB транзакцією |
| **CQRS** | Export Service читає через read-only API |
| **BFF (Backend for Frontend)** | Окремі gateway для Mobile, Desktop, Public API |
| **Circuit Breaker** | Захист від каскадних відмов між сервісами |
| **Event Sourcing** | Immutable лог подій відстеження доставки |
| **Idempotency Key** | Захист від дублювання webhook від Stripe/LiqPay |

### BFF шар

```
Mobile App    →  BFF Mobile   (порт 8010)  ─┐
Desktop Web   →  BFF Desktop  (порт 8011)  ─┼──→  Мікросервіси
Public API    →  BFF Public   (порт 8012)  ─┘
```

### Черга повідомлень

**RabbitMQ** (замінює поточний `doctrine://` transport Symfony Messenger):
- Topic exchanges на домен: `user.events`, `order.events`, `payment.events` тощо
- 24 типи подій між сервісами (повний список у каталозі подій)

### Документація

| Файл | Зміст |
|---|---|
| [`docs/microservices-architecture.md`](docs/microservices-architecture.md) | Повна архітектурна документація: межі сервісів, API endpoints, схеми БД, комунікація, безпека, стратегія міграції |
| [`docs/event-catalog.md`](docs/event-catalog.md) | Каталог 24 асинхронних подій зі схемами payload та sequence diagrams |

---

## Task 22–23: Файлове сховище (AWS S3)

**Task 22** додає **абстракцію файлового сховища** (локальна ФС або **Amazon S3**) через **AWS SDK для PHP** та **Flysystem**.

- Інтерфейс `FileStorageInterface`: `write`, `read`, `delete`, `exists`, `listKeys`, `publicUrl`
- Фабрика `FileStorageFactory` перемикається через `STORAGE_TYPE=local|s3`
- Presigned PUT URL для прямого завантаження в S3 з браузера
- Детальна документація: [`docs/STORAGE_SETUP.md`](docs/STORAGE_SETUP.md)

---

## Функціонал

- **Каталог товарів**: перегляд витратних матеріалів для друку за категоріями (inkjet / laser / dot-matrix) з атрибутами
- **Файлове сховище**: локальне або S3 для зображень товарів
- **Кошик покупок**: гібридний кошик (сесія для гостей, БД для авторизованих)
- **Аутентифікація**: JWT-токени для API, реєстрація та вхід для веб-інтерфейсу
- **OAuth 2.0**: вхід через Google та GitHub акаунти
- **REST API**: повноцінний CRUD для товарів, категорій, замовлень, користувачів
- **Документація API**: Swagger UI (OpenAPI 3.0) за адресою `/api/docs`
- **Адмін-панель**: EasyAdmin для керування контентом та експорту даних
- **Async Export**: експорт даних у CSV/JSON/XML через чергу повідомлень
- **Чиста архітектура**: Domain-Driven Design з окремими доменами

---

## Вимоги

- Docker та Docker Compose
- Git

---

## Встановлення та запуск

### 1. Клонувати репозиторій
```bash
git clone <repository-url>
cd task-24
```

### 2. Налаштувати змінні середовища
```bash
cp .env .env.local
```

Відредагуйте `.env.local`:

```env
# Google OAuth
GOOGLE_CLIENT_ID=ваш_google_client_id
GOOGLE_CLIENT_SECRET=ваш_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback

# GitHub OAuth
GITHUB_CLIENT_ID=ваш_github_client_id
GITHUB_CLIENT_SECRET=ваш_github_client_secret
GITHUB_REDIRECT_URI=http://localhost:8080/auth/github/callback
```

Для **S3** додайте:

```env
STORAGE_TYPE=s3
AWS_ACCESS_KEY_ID=your-key-id
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=eu-north-1
AWS_S3_BUCKET=your-bucket-name
```

Для **локального** сховища: `STORAGE_TYPE=local`

### 3. Зібрати образи та запустити контейнери

```bash
docker compose build php
docker compose up -d
docker compose exec php composer install
```

### 4. Налаштувати базу даних та ключі JWT

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

Сайт доступний за адресою: [http://localhost:8080](http://localhost:8080)

---

## Аутентифікація

### JWT API

```bash
# Отримати токен
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin@example.com", "password": "admin123"}'

# Використати токен
curl http://localhost:8080/api/products \
  -H "Authorization: Bearer <ваш_токен>"
```

### Тестові облікові дані

| Роль | Email | Пароль |
|---|---|---|
| Адміністратор | `admin@example.com` | `admin123` |
| Користувач | `user@example.com` | `user123` |

### OAuth 2.0

- **Google**: [console.cloud.google.com](https://console.cloud.google.com) → Credentials → OAuth 2.0 Client ID
- **GitHub**: [github.com/settings/developers](https://github.com/settings/developers) → New OAuth App

Callback URL для обох: `http://localhost:8080/auth/{google|github}/callback`

---

## API документація

Swagger UI: [http://localhost:8080/api/docs](http://localhost:8080/api/docs)

| Ресурс | Endpoint | Методи |
|---|---|---|
| Категорії | `/api/categories` | GET, POST, PATCH, DELETE |
| Товари | `/api/products` | GET, POST, PATCH, DELETE |
| Атрибути товарів | `/api/product_attributes` | GET, POST, PATCH, DELETE |
| Замовлення | `/api/orders` | GET, POST, PATCH, DELETE |
| Елементи замовлень | `/api/order_items` | GET, POST, PATCH, DELETE |
| Користувачі | `/api/users` | GET, POST |

Підтримувані формати: `application/json`, `application/ld+json`, `application/xml`

---

## Структура проекту

```
src/
├── Catalog/Domain/Entity/    Продукти, категорії, атрибути
├── Cart/Domain/Entity/       Кошик та елементи кошика
├── Order/Domain/Entity/      Замовлення та елементи замовлень
├── User/Domain/Entity/       Користувачі
├── Export/                   Async export jobs (Saga, handlers, formatters)
├── Storage/                  Абстракція файлового сховища (S3 / local)
├── Controller/               HTTP контролери (веб + адмін + OAuth)
├── Repository/               Doctrine репозиторії
├── Service/                  Бізнес-логіка (CartService, ProductImageService)
└── EventListener/            LoginListener (міграція кошика при вході)

docs/
├── microservices-architecture.md   Мікросервісна архітектура (Task 24)
├── event-catalog.md                Каталог подій RabbitMQ (Task 24)
└── STORAGE_SETUP.md                Налаштування S3 / local storage (Task 22)
```

---

## Тестування

```bash
# Всі тести
docker compose exec php php bin/phpunit

# За суітами
docker compose exec php php bin/phpunit --testsuite Unit
docker compose exec php php bin/phpunit --testsuite Functional

# Окремі директорії
docker compose exec php php bin/phpunit tests/Unit/Storage/
docker compose exec php php bin/phpunit tests/Functional/Admin/

# Діагностика S3
docker compose exec php php bin/console app:verify-storage
```

---

## Використані пакети

| Пакет | Призначення |
|---|---|
| `symfony/framework-bundle` ^7.4 | Основний фреймворк |
| `api-platform/core` ^4.2 | REST API (OpenAPI 3.0) |
| `easycorp/easyadmin-bundle` ^4.27 | Адмін-панель |
| `lexik/jwt-authentication-bundle` | JWT автентифікація |
| `doctrine/orm` ^3.6 | ORM |
| `league/oauth2-google` ^4.1 | Google OAuth 2.0 |
| `league/oauth2-github` ^3.1 | GitHub OAuth 2.0 |
| `aws/aws-sdk-php` ^3.0 | AWS SDK (S3, presigned URL) |
| `league/flysystem` 3.x | Абстракція файлової системи |
| `league/flysystem-aws-s3-v3` 3.x | Flysystem адаптер для S3 |
| `symfony/messenger` | Async черга повідомлень |

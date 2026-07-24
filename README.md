# PrintZone — інтернет-магазин витратних матеріалів для друку (Task-25)

Symfony-додаток електронної комерції (друкарський магазин: картриджі, тонери, drum units, стрічки) з чистою архітектурою, принципами SOLID та Domain-Driven Design.
Включає **REST API** на базі API Platform із захистом через **JWT-токени**, **OAuth 2.0** (Google, GitHub) та **адмін-панель** EasyAdmin.

**Task 25** — **виконання** мікросервісної архітектури, спроектованої в Task 24: моноліт розділено на незалежні сервіси за межами DDD, кожен зі своєю базою даних, середовищами (dev / test / prod) та власним CI/CD-пайплайном.

> Task 24 (`docs/microservices-architecture.md`) — це *аналіз і планування*. Task 25 — це *реалізація й деплой*: сервіси нижче реально побудовані, протестовані й працюють на проді.

---

## Task 25: Реалізована мікросервісна архітектура

Застосовано патерн **Strangler Fig** — моноліт поступово «обрізали», виносячи домен за доменом у окремий сервіс, доки кожен обмежений контекст (bounded context) не отримав власний код, БД і пайплайн. Моноліт лишився тонким web/admin-фронтом, що спілкується із сервісами.

### Сервіси

| Сервіс | Порт (dev) | Відповідальність | Власна БД | Стан |
|---|---|---|---|---|
| **User Service** | 8001 | Користувачі, автентифікація, JWT, OAuth | `db-user` | ✅ prod |
| **Catalog Service** | 8002 | Продукти, категорії, бренди, атрибути | `db-catalog` | ✅ prod |
| **Order Service** | 8003 | Замовлення, lifecycle статусів, checkout | `db-order` | ✅ prod |
| **Cart Service** | 8004 | Кошик авторизованих користувачів | `db-cart` | ✅ prod |
| **Payment Service** | 8005 | Оплата, webhook Stripe, події оплати | `db-payment` | ✅ prod |
| **Delivery Service** | 8006 | Відправлення, event-sourced трекінг (Нова Пошта / fake) | `db-delivery` | ✅ prod |
| **Notification Service** | 8007 | Email-сповіщення (stateless consumer) | — | ✅ prod¹ |
| **Storage Service** | 8008 | Файли S3 / local (stateless об'єктний бекенд) | — | ✅ prod |
| **Export Service** | 8009 | Async CSV/JSON/XML export jobs | `db-export` | ✅ prod |
| **Monolith (web/admin)** | 8080 | Тонкий фронт: сторінки магазину, EasyAdmin, OAuth, гостьовий кошик | `database` | ✅ prod |

> ¹ Notification Service задеплоєний і працює; доставка листів реальним покупцям потребує виводу Amazon SES із sandbox (production access). Деталі — див. нижче «Обмеження».

**User, Catalog, Cart, Order, Payment, Export, Storage** витягнуто з наявних модулів моноліту. **Delivery** і **Notification** — greenfield-сервіси (у моноліті не було окремих доменів доставки/сповіщень).

### Комунікація між сервісами

- **Синхронна (HTTP + S2S JWT):** сервіс підписує service-to-service JWT спільним ключем і викликає REST-ендпойнти іншого (напр. Export → Catalog/Order/User/Storage). Незмінний seam `FileStorageInterface` дозволив підмінити локальне сховище на Storage Service без правок споживачів.
- **Асинхронна (RabbitMQ):** доменні події публікуються в topic exchanges (`order.*`, `payment.*`, `shipment.*`) через **Outbox Pattern** (гарантована доставка в межах DB-транзакції). Приклад ланцюжка checkout (**Choreography Saga**):

```
OrderPaid → Payment Service (webhook Stripe) 
          → Order Service (PAID) 
          → Delivery Service (Shipment + tracking) 
          → Notification Service (email-квитанція)
```

### API-специфікації (OpenAPI)

Машиночитані контракти 4 ключових сервісів (OpenAPI 3.1) лежать поряд із кожним сервісом:

| Сервіс | Специфікація |
|---|---|
| User | `services/user-service/openapi.yaml` |
| Catalog | `services/catalog-service/openapi.yaml` |
| Order | `services/order-service/openapi.yaml` |
| Cart | `services/cart-service/openapi.yaml` |

Переглянути всі разом через Swagger UI: `docs/openapi/index.html` (інструкції та валідація — `docs/openapi/README.md`).

### Застосовані патерни (реалізовані)

| Патерн | Де |
|---|---|
| **Strangler Fig** | Поетапне винесення доменів з моноліту |
| **Database per Service** | Окрема PostgreSQL на кожен stateful-сервіс |
| **Outbox Pattern** | Публікація подій разом із DB-транзакцією (delivery/order) |
| **Choreography Saga** | Checkout: оплата → замовлення → доставка → сповіщення |
| **Event Sourcing** | Immutable `tracking_events` у Delivery Service |
| **Idempotency** | Захист від повторних webhook/подій (at-least-once) |
| **CQRS-стиль** | Export читає дані через read-only API інших сервісів |

> **BFF-шар** (Backend-for-Frontend для Mobile/Desktop/Public) був частиною *плану* Task 24, але в Task 25 **не реалізований** — фронт обслуговує моноліт.

---

## Домен-керована розробка (DDD)

Кожен сервіс володіє власним обмеженим контекстом; крос-сервісних FK немає. Типова структура сервісу:

```
services/<name>/src/
├── Entity/                Доменні сутності (Doctrine)
├── Repository/            Репозиторії
├── Controller/            HTTP-ендпойнти + health
├── MessageHandler/        Обробники подій/команд (Symfony Messenger)
├── Messaging/Domain/      Integration events
└── Service|Client/        Доменні сервіси та S2S-клієнти
```

Моноліт зберігає повну модульну DDD-розкладку (`src/<Module>/Domain/Entity/`).

---

## Вимоги

- Docker та Docker Compose
- Git

---

## Встановлення та запуск (моноліт)

### 1. Клонувати репозиторій
```bash
git clone <repository-url>
cd task-25
```

### 2. Налаштувати змінні середовища
```bash
cp .env .env.local
```

Відредагуйте `.env.local` (OAuth, за потреби — S3):

```env
# Google / GitHub OAuth
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...

# Сховище (local | s3)
STORAGE_TYPE=local
# для S3:
# STORAGE_TYPE=s3
# AWS_ACCESS_KEY_ID=...
# AWS_SECRET_ACCESS_KEY=...
# AWS_DEFAULT_REGION=eu-north-1
# AWS_S3_BUCKET=...
```

### 3. Зібрати та запустити
```bash
docker compose build php
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

Сайт: [http://localhost:8080](http://localhost:8080)

### 4. Запуск окремого сервісу

Кожен сервіс самодостатній — має власний `compose.yaml`, `Dockerfile` та `.env`:

```bash
cd services/catalog-service
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Сервіси приєднуються до спільної мережі `task-25_default` і резолвляться за іменем (`http://catalog-service`).

---

## Середовища (ізоляція dev / test / prod)

Кожен сервіс має три ізольовані конфігурації:

| Середовище | Файл | Особливості |
|---|---|---|
| **dev** | `compose.yaml` + `.env` | Локальний Docker, Mailpit для пошти, порт 800x |
| **test** | `.env.test` | In-memory SQLite, ізольовані фікстури |
| **prod** | `compose.prod.yaml` | Оверлей: prod-образ, секрети з CI, без публічних портів |

---

## Тестування

```bash
# Моноліт
docker compose exec php php bin/phpunit
docker compose exec php php bin/phpunit --testsuite Unit
docker compose exec php php bin/phpunit --testsuite Functional

# Окремий сервіс (binary — vendor/bin/phpunit)
cd services/order-service
docker compose exec php vendor/bin/phpunit
```

Інтеграція перевіряється e2e: checkout → оплата (Stripe) → PAID → відправлення → DELIVERED, а також export → генерація файлу → email.

---

## Деплой (незалежний CI/CD)

GitLab CI: кожен сервіс має власні `build` / `test` / `deploy:<service>` джоби — сервіси деплояться **незалежно** один від одного:

```
deploy:user-service      deploy:catalog-service   deploy:order-service
deploy:cart-service      deploy:payment-service   deploy:delivery-service
deploy:notification-service   deploy:storage-service   deploy:export-service
```

Прод — один DigitalOcean droplet; сервіси живуть під деревом моноліту (`/var/www/app/services/<name>`), спільна мережа `task-25_default`, спільний JWT-keypair. Merge у `develop` → пайплайн збирає, тестує й деплоїть змінені сервіси.

---

## Аутентифікація

### JWT API
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin@example.com", "password": "admin123"}'

curl http://localhost:8080/api/products -H "Authorization: Bearer <токен>"
```

### Тестові облікові дані

| Роль | Email | Пароль |
|---|---|---|
| Адміністратор | `admin@example.com` | `admin123` |
| Користувач | `user@example.com` | `user123` |

### OAuth 2.0
Callback: `http://localhost:8080/auth/{google|github}/callback`

---

## API документація

Swagger UI: [http://localhost:8080/api/docs](http://localhost:8080/api/docs)

Формати: `application/json`, `application/ld+json`, `application/xml`

---

## Структура репозиторію

```
src/                       Моноліт (web/admin фронт, тонкі клієнти до сервісів)
├── <Module>/Domain/Entity/   DDD-модулі
├── Controller/               Веб, адмін, OAuth
├── Export/Client, Storage/Client   S2S-клієнти до сервісів
└── ...

services/                  Мікросервіси (кожен — самодостатній Symfony-застосунок)
├── user-service/          :8001
├── catalog-service/       :8002
├── order-service/         :8003
├── cart-service/          :8004
├── payment-service/       :8005
├── delivery-service/      :8006
├── notification-service/  :8007
├── storage-service/       :8008
└── export-service/        :8009

docs/
├── microservices-architecture.md   Проєктування архітектури (Task 24)
├── microservices-analysis.md       Аналіз меж сервісів
├── event-catalog.md                Каталог подій RabbitMQ
├── STORAGE_SETUP.md                Налаштування S3 / local
└── todo.md                         План і хід робіт
```

---

## Обмеження / технічний борг

- **Amazon SES sandbox** — export-листи адміну доходять (адреса верифікована); квитанції покупцям із Notification Service потребують SES production access (довільні адреси в sandbox відхиляються).
- **Notification Service** потребує додавання `symfony/amazon-mailer` + verified-domain sender + best-effort надсилання перед виходом із sandbox (див. `docs/todo.md`).
- **RabbitMQ teardown** — моноліт досі тримає catch-all `events_all` як log-only observer (свідоме рішення, щоб не осиротити prod-чергу).

---

## Технологічний стек

| Пакет | Призначення |
|---|---|
| `symfony/framework-bundle` ^7.4 | Основний фреймворк |
| `api-platform/core` ^4.x | REST API (OpenAPI 3.0) |
| `easycorp/easyadmin-bundle` ^4.x | Адмін-панель |
| `lexik/jwt-authentication-bundle` | JWT (API + S2S) |
| `doctrine/orm` ^3.x | ORM, database-per-service |
| `symfony/messenger` | Async черга (RabbitMQ / doctrine transport) |
| `league/oauth2-google`, `league/oauth2-github` | OAuth 2.0 |
| `aws/aws-sdk-php` ^3.x + `league/flysystem` | S3 / local сховище, presigned URL |
| `symfony/amazon-mailer` | SES-доставка email (export-service) |

**Інфраструктура:** PHP 8.2+, PostgreSQL 16, RabbitMQ, Docker Compose, GitLab CI/CD, DigitalOcean.

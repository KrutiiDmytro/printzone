# Інтернет-магазин електроніки (Task-22)

Symfony-додаток електронної комерції з чистою архітектурою, принципами SOLID та сучасними найкращими практиками.
Включає **REST API** на базі API Platform із захистом через **JWT-токени**, а також **OAuth 2.0 аутентифікацію** через Google та GitHub.

**Task 22** додає **абстракцію файлового сховища** (локальна ФС або **Amazon S3**) через **AWS SDK для PHP** та **Flysystem** — детальний опис нижче та у [`docs/STORAGE_SETUP.md`](docs/STORAGE_SETUP.md).

## 📦 Task 22: інтеграція AWS S3 та абстракція файлової системи

Цей блок відповідає вимогам завдання: інтеграція SDK, конфігурація, єдиний API для local/S3, перемикання, тести та документація.

### Інтеграція AWS SDK (Composer)

- Пакети: **`aws/aws-sdk-php`**, **`league/flysystem`**, **`league/flysystem-aws-s3-v3`** (див. `composer.json`).
- Клієнт AWS налаштовується у `config/packages/aws.yaml` — регіон, версія API, облікові дані з **змінних середовища** (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_S3_BUCKET`, `AWS_S3_VERSION`).

### Облікові дані та параметри S3 у Symfony

- Значення задаються в **`.env`** / **`.env.local`** (секрети — лише в `.env.local`).
- Для S3 потрібні змінні `AWS_*` та **`STORAGE_TYPE=s3`** (див. наступний підрозділ).
- Повний перелік змінних і операційних кроків — у **[`docs/STORAGE_SETUP.md`](docs/STORAGE_SETUP.md)**.

### Абстракція файлової системи (Flysystem)

- Інтерфейс **`App\Storage\FileStorageInterface`**: завантаження (`write`), читання (`read`), видалення (`delete`), перевірка наявності (`exists`), перелік **файлів** за префіксом (`listKeys`), публічний URL (`publicUrl`).
- Реалізація **`App\Storage\FlysystemFileStorage`** працює поверх **League Flysystem** (локальний адаптер або **AwsS3V3Adapter**).
- Фабрика **`App\Storage\FileStorageFactory`** створює потрібний екземпляр залежно від конфігурації; сервіс у контейнері: `config/packages/storage.yaml`.

### Операції та узгодженість local / S3

- Один і той самий контракт **для обох** бекендів: ті самі ключі (наприклад `products/{id}-...`).
- У режимі **local** зображення для вітрини можуть віддаватися через маршрут **`GET /media?key=...`** (`MediaController`).
- У режимі **S3** для відображення використовується **пряме посилання** на об’єкт (`publicUrl`), коли налаштовано клієнт і бакет.

### Інтеграція з додатком — пряме завантаження в S3

**`STORAGE_TYPE=s3`** — EasyAdmin використовує **presigned PUT** для прямого завантаження файлу в S3 з браузера:

1. Форма товару містить прихований текстовий ключ S3 + вбудований вибір файлу (скрипт **`public/js/admin-product-image-s3.js`**).
2. Після вибору файлу JS звертається до **`POST /admin/product-image/presign`** (`ProductImagePresignController`) → отримує **presigned PUT URL** і ключ виду `products/{uuid}-{ім'я}`.
3. JS виконує **PUT** безпосередньо на S3 (минаючи сервер) → об'єкт з'являється в бакеті.
4. Ключ записується у приховане поле → при «Зберегти» зберігається в БД.
5. Відображення картинок: через **`GET /media?key=...`** (`MediaController`) — бакет лишається приватним.

**Вимоги для S3:**

| Що | Де налаштувати |
|---|---|
| IAM: `s3:PutObject`, `s3:GetObject`, `s3:DeleteObject` на `products/*` | AWS Console → IAM |
| CORS: `PUT`, `GET`, `HEAD` для origin адмінки | AWS Console → S3 → Permissions → CORS |
| Регіон бакета = `AWS_DEFAULT_REGION` | `.env.local` |

Приклад CORS:
```json
[{ "AllowedHeaders": ["*"], "AllowedMethods": ["PUT","GET","HEAD"], "AllowedOrigins": ["http://localhost","https://e-commerce.it.com"], "ExposeHeaders": ["ETag"], "MaxAgeSeconds": 3000 }]
```

**`STORAGE_TYPE=local`** — EasyAdmin зберігає файл у **`var/tmp/ea-product-uploads/`**, далі **`ProductImageService`** записує у `var/storage`; URL через `/media?key=...`.

### Перемикання між файловими системами

- Параметр **`STORAGE_TYPE`**: `local` (за замовчуванням — локальний каталог `var/storage`) або **`s3`**.
- Перемикання без зміни коду бізнес-логіки — лише через **змінні середовища** та `cache:clear` після зміни.
- Додаткові пояснення: **перемикання**, **Docker**, **типові помилки** — у [`docs/STORAGE_SETUP.md`](docs/STORAGE_SETUP.md).

### Тестування та TDD

- Юніт-тести сховища: **`tests/Unit/Storage/`** — локальний адаптер, фабрика, сценарії для S3 через **`Aws\MockHandler`** (повний цикл без реального бакета).
- Рекомендований підхід завдання: **TDD** — спочатку тести для контракту сховища (завантаження, вилучення/читання, перелік, видалення) для **local** і **S3**, потім/паралельно реалізація.
- Запуск усіх тестів:
  ```bash
  php bin/phpunit
  ```
  або в Docker:
  ```bash
  docker compose exec php php bin/phpunit
  ```

### Документація

- Операційні процедури, налаштування, перемикання — **[`docs/STORAGE_SETUP.md`](docs/STORAGE_SETUP.md)**.

---

## 🚀 Функціонал

- **Каталог товарів**: Перегляд електроніки за категоріями з атрибутами
- **Файлове сховище**: локальне або S3 для зображень товарів (Task 22)
- **Аутентифікація**: JWT-токени для захисту API, реєстрація та вхід для веб-інтерфейсу
- **OAuth 2.0**: Вхід через Google та GitHub акаунти
- **REST API**: Повноцінний CRUD для товарів, категорій, замовлень, користувачів
- **Документація API**: Автоматично згенерована Swagger UI (OpenAPI 3.0)
- **Формати відповідей**: JSON, JSON-LD, XML
- **Кошик покупок**: Гібридний кошик (сесія для гостей, БД для авторизованих)
- **Адмін-панель**: EasyAdmin для керування контентом
- **Чиста архітектура**: Domain-Driven Design з окремими доменами

## 📋 Вимоги

- Docker & Docker Compose
- Git

## 🛠️ Встановлення та Запуск

### 1. Клонувати репозиторій
```bash
git clone <repository-url>
cd Task-22
```

### 2. Налаштувати змінні середовища
```bash
cp .env .env.local
```

Відредагуйте `.env.local` та вкажіть ваші OAuth credentials:

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

Для **S3** (Task 22) додайте:

```env
STORAGE_TYPE=s3
AWS_ACCESS_KEY_ID=your-key-id
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=eu-north-1
AWS_S3_BUCKET=your-bucket-name
AWS_S3_VERSION=latest
```

> **Важливо:** `AWS_DEFAULT_REGION` має точно збігатися з регіоном, у якому створено бакет. Перевірити: AWS Console → S3 → ваш бакет → назва регіону поруч з іменем.

Для **локального** сховища: `STORAGE_TYPE=local` (деталі в [`docs/STORAGE_SETUP.md`](docs/STORAGE_SETUP.md)).

### 3. Зібрати образ PHP, запустити контейнери та встановити залежності

Перший запуск або після змін у `Dockerfile.php`:

```bash
docker compose build php
docker compose up -d
docker compose exec php composer install
```

### 4. Налаштувати базу даних та ключі JWT
```bash
# Виконати міграції
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Завантажити тестові дані (категорії, товари, користувачі)
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

# Згенерувати ключі для JWT
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

Тепер сайт доступний за адресою: [http://localhost:8080](http://localhost:8080)

---

## 🔐 OAuth 2.0 Аутентифікація

### Вхід через Google

#### Налаштування Google Cloud Console:
1. Відкрийте [console.cloud.google.com](https://console.cloud.google.com)
2. Перейдіть в **APIs & Services → Credentials**
3. Натисніть **Create Credentials → OAuth 2.0 Client ID**
4. Тип: **Web application**
5. Додайте в **Authorized redirect URIs**:
   ```
   http://localhost:8080/auth/google/callback
   ```
6. Збережіть `Client ID` та `Client Secret` в `.env`

#### Використання:
- Відкрийте [http://localhost:8080/login](http://localhost:8080/login)
- Натисніть кнопку **"Sign in with Google"**
- Авторизуйтесь через Google акаунт
- Після успішного входу ви будете перенаправлені на головну сторінку

---

### Вхід через GitHub

#### Налаштування GitHub OAuth App:
1. Відкрийте [github.com/settings/developers](https://github.com/settings/developers)
2. Натисніть **New OAuth App**
3. Заповніть форму:
   - **Application name**: `Task-21`
   - **Homepage URL**: `http://localhost:8080`
   - **Authorization callback URL**: `http://localhost:8080/auth/github/callback`
4. Збережіть `Client ID` та `Client Secret` в `.env`

#### Використання:
- Відкрийте [http://localhost:8080/login](http://localhost:8080/login)
- Натисніть кнопку **"Sign in with GitHub"**
- Авторизуйтесь через GitHub акаунт
- Після успішного входу ви будете перенаправлені на головну сторінку

---

## 🔑 Аутентифікація через JWT

API захищено JWT-токенами. Для доступу до захищених ресурсів потрібно отримати токен.

### 1. Отримання токена

Відправте POST-запит на `/api/login`:

```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"username": "admin@example.com", "password": "admin123"}'
```

**Відповідь:**
```json
{"token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."}
```

### 2. Використання токена

Додайте заголовок до кожного захищеного запиту:
```
Authorization: Bearer <ваш_токен>
```

### 3. Дані за замовчуванням (тестові)

| Роль | Email | Пароль |
|------|-------|--------|
| Адміністратор | `admin@example.com` | `admin123` |
| Користувач | `user@example.com` | `user123` |

---

## 📚 Документація API (Swagger UI)

Відкрийте в браузері: [http://localhost:8080/api/docs](http://localhost:8080/api/docs)

1. Натисніть кнопку **Authorize** (🔓 вгорі праворуч).
2. Введіть токен у форматі: `Bearer <ваш_токен>`.
3. Натисніть **Authorize** → **Close**.
4. Тестуйте будь-які ендпоінти.

### Доступні ресурси API

| Ресурс | Ендпоінт | Методи |
|--------|----------|--------|
| Категорії | `/api/categories` | GET, POST, PATCH, DELETE |
| Товари | `/api/products` | GET, POST, PATCH, DELETE |
| Атрибути товарів | `/api/product_attributes` | GET, POST, PATCH, DELETE |
| Замовлення | `/api/orders` | GET, POST, PATCH, DELETE |
| Елементи замовлень | `/api/order_items` | GET, POST, PATCH, DELETE |
| Користувачі | `/api/users` | GET, POST |

### Формати відповідей

API підтримує кілька форматів. Вкажіть потрібний в заголовку `Accept`:

| Формат | Accept Header |
|--------|--------------|
| JSON | `application/json` |
| JSON-LD | `application/ld+json` |
| XML | `application/xml` |

---

## 📁 Структура проекту

```
src/
├── Cart/Domain/Entity/          # Сутності кошика
├── Catalog/Domain/Entity/       # Товари, категорії, атрибути
├── Order/Domain/Entity/         # Замовлення та елементи замовлень
├── User/Domain/Entity/          # Користувачі
├── Storage/                     # Абстракція файлового сховища (Flysystem, S3/local)
├── Controller/                  # Контролери (веб + API)
│   ├── GoogleAuthController.php # OAuth контролер для Google
│   └── GitHubAuthController.php # OAuth контролер для GitHub
├── Repository/                  # Репозиторії Doctrine
├── Service/                     # Бізнес-логіка (CartService, ProductImageService, ProductImagePresignService)
├── EventListener/               # Слухачі подій (LoginListener)
└── DataFixtures/                # Тестові дані
```

---

## 🧪 Тестування

### Запустити всі тести:
```bash
docker compose exec php php bin/phpunit
```

### Запустити тільки OAuth тести:
```bash
docker compose exec php php bin/phpunit tests/Functional/Controller/OAuthControllerTest.php
```

### Запустити Unit тести:
```bash
docker compose exec php php bin/phpunit tests/Unit/
```

### Тести сховища (Task 22):
```bash
docker compose exec php php bin/phpunit tests/Unit/Storage/
```

### Тести адмінки (presign, CRUD):
```bash
docker compose exec php php bin/phpunit tests/Functional/Admin/
```

### Приклад успішного прогону:
```
OK (75 tests, 174 assertions)
```
*(фактичні числа залежать від версії тестів)*

### Діагностика S3:
```bash
docker compose exec php php bin/console app:verify-storage
```
Показує STORAGE_TYPE, регіон, бакет і список об'єктів `products/*` у S3.

---

## 🌐 Production Розгортання

Додаток розгорнуто на DigitalOcean VPS з SSL сертифікатом:

**URL**: [https://e-commerce.it.com](https://e-commerce.it.com)

### Налаштування для Production:

1. **SSL сертифікат** (Let's Encrypt):
```bash
certbot certonly --standalone -d e-commerce.it.com -d www.e-commerce.it.com
```

2. **Оновіть `.env`** на сервері:
```env
DEFAULT_URI=https://e-commerce.it.com
GOOGLE_REDIRECT_URI=https://e-commerce.it.com/auth/google/callback
GITHUB_REDIRECT_URI=https://e-commerce.it.com/auth/github/callback
```

3. **Запустіть контейнери**:
```bash
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

4. **Для S3** на продакшні налаштуйте CORS на бакеті (`AllowedOrigins`: домен вашого сайту) та IAM-політику для `products/*`.


## 📦 Використані пакети

| Пакет | Версія | Призначення |
|-------|--------|-------------|
| `aws/aws-sdk-php` | ^3.0 | AWS SDK (S3-клієнт, presigned URL) |
| `league/flysystem` | 3.x | Абстракція файлової системи |
| `league/flysystem-aws-s3-v3` | 3.x | Адаптер Flysystem для S3 |
| `league/oauth2-google` | ^4.1 | Google OAuth 2.0 |
| `league/oauth2-github` | ^3.1 | GitHub OAuth 2.0 |
| `lexik/jwt-authentication-bundle` | * | JWT аутентифікація |
| `api-platform/core` | ^4.2 | REST API |
| `easycorp/easyadmin-bundle` | ^4.27 | Адмін-панель |
| `doctrine/orm` | ^3.6 | ORM |

# Інтернет-магазин електроніки (Task-21)

Symfony-додаток електронної комерції з чистою архітектурою, принципами SOLID та сучасними найкращими практиками.
Включає **REST API** на базі API Platform із захистом через **JWT-токени**, а також **OAuth 2.0 аутентифікацію** через Google та GitHub.

## 🚀 Функціонал

- **Каталог товарів**: Перегляд електроніки за категоріями з атрибутами
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
cd Task-21
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

### 3. Запустити Docker контейнери
```bash
docker compose up -d
```

### 4. Встановити залежності
```bash
docker compose exec php composer install
```

### 5. Налаштувати базу даних та ключі JWT
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
├── Controller/                  # Контролери (веб + API)
│   ├── GoogleAuthController.php # OAuth контролер для Google
│   └── GitHubAuthController.php # OAuth контролер для GitHub
├── Repository/                  # Репозиторії Doctrine
├── Service/                     # Бізнес-логіка (CartService)
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

### Результат тестування:
```
OK (51 tests, 122 assertions)
```

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
docker-compose up -d
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```


## 📦 Використані пакети

| Пакет | Версія | Призначення |
|-------|--------|-------------|
| `league/oauth2-google` | ^4.1 | Google OAuth 2.0 |
| `league/oauth2-github` | ^3.1 | GitHub OAuth 2.0 |
| `lexik/jwt-authentication-bundle` | * | JWT аутентифікація |
| `api-platform/core` | ^4.2 | REST API |
| `easycorp/easyadmin-bundle` | ^4.27 | Адмін-панель |
| `doctrine/orm` | ^3.6 | ORM |

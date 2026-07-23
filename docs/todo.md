# OpenAPI-специфікації для 4 ключових сервісів

> Активний робочий план. Попередні плани фаз (Export — Phase 7; Storage — MR !32; Notification та ін.) — в git-історії.

## Мета

Закрити прогалину SOA-документації: додати **машиночитані OpenAPI-контракти** для 4 ключових
сервісів. Декомпозиція вже виконана й задеплоєна, уся дизайн-документація існує
(`microservices-architecture.md`, `-analysis.md`, `event-catalog.md`), але окремі мікросервіси
працюють на звичайних Symfony-контролерах **без OpenAPI**. Моноліт генерує OpenAPI в runtime
через API Platform — сервіси ні.

## Рішення

- **Обсяг:** User (:8001), Catalog (:8002), Order (:8003), Cart (:8004) — ядро e-commerce
  («2-4 key services» з формулювання мети).
- **Підхід:** вручну написані статичні `openapi.yaml` (OpenAPI 3.1.0) у кожному сервісі.
  БЕЗ нових залежностей, БЕЗ змін у контролерах/конфігах.
- **Перегляд:** Swagger UI з CDN у `docs/openapi/index.html`.
- **Гілка:** `docs/openapi-specs` від `origin/develop`.

## Чекліст

- [x] Крок 0: гілка `docs/openapi-specs` від `origin/develop` (стороння зміна `config/reference.php` не чіпається)
- [x] `services/user-service/openapi.yaml` (auth, users, health)
- [x] `services/catalog-service/openapi.yaml` (products, categories, brands, health)
- [x] `services/order-service/openapi.yaml` (orders, checkout, health)
- [x] `services/cart-service/openapi.yaml` (cart, health)
- [x] `docs/openapi/index.html` — Swagger UI (перемикач 4 спек)
- [x] `docs/openapi/README.md` — інструкція перегляду + валідації
- [x] Верифікація: `redocly lint` усіх 4 → 0 errors
- [x] Звірка `#[Route]` ↔ операції по кожному контролеру
- [ ] (Опційно) посилання на спеки в кореневому `README.md`
- [ ] Комміт лише нових файлів (не `-A` — виключити `config/reference.php`)

## Review (результати)

- **Створено 6 нових файлів**, жоден існуючий не редаговано; runtime сервісів не змінено.
- **`redocly lint`: усі 4 спеки валідні** (лише косметичні warnings — відсутня license,
  описи тегів, 4xx на health-probe). 0 errors.
- **Виправлені під час валідації дефекти:**
  1. Двокрапка+пробіл у незакавичених `description` (`Authorization: Bearer`, `items: []`)
     ламала YAML-парсинг → взято в лапки.
  2. `no-identical-paths`: `/{slug}` і `/{id}` — однаковий шаблон для OpenAPI (Symfony
     розрізняє методом). Об'єднано GET-by-slug і мутації в один path item `/{id}`
     з path-параметром-рядком (categories, brands).
- **Покриття endpoints (звірено з контролерами):** user 4+2health, catalog 13+2health,
  order 4+2health, cart 5+2health. Пропущених/зайвих шляхів немає.
- **Не увійшло (свідомо):** Payment/Delivery/Notification/Storage/Export — поза обсягом
  «ключових 4»; жодних змін у коді сервісів.

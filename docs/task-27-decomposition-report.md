# Task 27 — Декомпозиція e-commerce застосунку на сервіси (SOA)

> **Звіт-сабмішн для ментора.** Мета цього документа — показати, *що* зроблено по кожній
> вимозі завдання і *де саме* в репозиторії це лежить. Уся реалізація вже змерджена в
> `develop`; нижче — карта «вимога → артефакт».

**Мета завдання:** стратегічно декомпозувати наявний моноліт на набір сервісів у відповідності
до принципів Service-Oriented Architecture (SOA) та визначити 2–4 ключові сервіси для
першочергової розробки.

---

## Резюме

Моноліт PrintZone (Symfony 7.4) розрізано на **9 незалежних сервісів** за патерном
**Strangler Fig**, кожен зі своїм обмеженим контекстом і власною БД (database-per-service).
Як **2–4 ключові сервіси першочергової розробки** обрано ядро e-commerce:
**User · Catalog · Order · Cart** — саме для них написані машиночитані OpenAPI-контракти.

| Ключовий сервіс | Порт | Відповідальність | БД |
|---|---|---|---|
| **User** | 8001 | Користувачі, автентифікація, JWT, OAuth | `db-user` |
| **Catalog** | 8002 | Продукти, категорії, бренди, атрибути | `db-catalog` |
| **Order** | 8003 | Замовлення, lifecycle статусів, checkout | `db-order` |
| **Cart** | 8004 | Кошик авторизованих користувачів | `db-cart` |

Решта (Payment `:8005`, Delivery `:8006`, Notification `:8007`, Storage `:8008`,
Export `:8009`) — виділені пізніше, поза «ключовою четвіркою».

---

## 1. Аналіз компонентів застосунку

**Вимога:** виявити основні функції, придатні до модуляризації (Inventory, Order Processing,
User Management, Product Catalog).

**Зроблено** — див. [`docs/microservices-analysis.md`](microservices-analysis.md) §1:

- §1.2 — інвентаризація доменних сутностей із прив'язкою до коду (`Product`, `Category`,
  `Brand`, `Cart`, `Order`, `User`, `ExportJob`…).
- §1.3 — карта міжмодульної FK-зв'язності (що ускладнює розрізання).
- §1.4 — сегменти-кандидати в сервіси.

Виявлені основні функції: **User Management, Product Catalog, Cart, Order Processing,
Payment, Delivery, Notification, File Storage, Data Export**.

## 2. Визначення меж сервісів (SOA / DDD)

**Вимога:** чіткі межі, незалежна робота, взаємодія через визначені інтерфейси.

**Зроблено** — [`docs/microservices-analysis.md`](microservices-analysis.md) §2 +
[`docs/microservices-architecture.md`](microservices-architecture.md) §3:

- **Bounded contexts** і **context map** (§2.1–2.2).
- **Database per Service** (§2.3): кожен stateful-сервіс має власну PostgreSQL,
  **крос-сервісних FK немає** — зв'язок лише через API/події.
- У моноліті межі попередньо закріплені через per-service PostgreSQL-схеми
  (`#[ORM\Table(schema: '...')]`, див. `CLAUDE.md`), що спростило винесення.

## 3. Специфікація сервісів (OpenAPI)

**Вимога:** документувати обов'язки, endpoints і контракти даних; розглянути OpenAPI.

**Зроблено** — машиночитані контракти **OpenAPI 3.1** для 4 ключових сервісів
(`redocly lint` = 0 errors):

| Сервіс | Специфікація |
|---|---|
| User | [`services/user-service/openapi.yaml`](../services/user-service/openapi.yaml) |
| Catalog | [`services/catalog-service/openapi.yaml`](../services/catalog-service/openapi.yaml) |
| Order | [`services/order-service/openapi.yaml`](../services/order-service/openapi.yaml) |
| Cart | [`services/cart-service/openapi.yaml`](../services/cart-service/openapi.yaml) |

- Перегляд усіх разом через Swagger UI: [`docs/openapi/index.html`](openapi/index.html)
  (інструкції — [`docs/openapi/README.md`](openapi/README.md)).
- Детальний дизайн endpoints/схем кожного сервісу —
  [`docs/microservices-architecture.md`](microservices-architecture.md) §4.
- **Конвенції контрактів:** гроші — ціле в центах; ID — UUID; час — ISO-8601;
  помилки — `{ "error": "<message>" }`; авторизація `/api/*` — S2S RS256 JWT.

## 4. Планування міжсервісної взаємодії

**Вимога:** обрати протоколи для синхронної взаємодії (REST/SOAP) і систему обміну
повідомленнями для асинхронної (AMQP/MQTT).

**Рішення** (деталі — [`docs/microservices-architecture.md`](microservices-architecture.md) §5,
повна реалізація описана в **Task 26**, [`docs/task-26-communication-strategies.md`](task-26-communication-strategies.md)):

- **Синхронно — REST/HTTPS (не SOAP).** REST обрано через легкість (JSON, без WSDL/SOAP-обгортки),
  нативну підтримку в Symfony/API Platform і сумісність із OpenAPI. S2S-виклики
  автентифікуються RS256-JWT. Клієнти в моноліті: `src/*/Client/*Client.php`.
- **Асинхронно — AMQP (RabbitMQ), не MQTT.** MQTT орієнтований на IoT/телеметрію;
  для бізнес-подій із гарантіями доставки й topic-маршрутизацією обрано AMQP. Доменні
  події публікуються в topic exchange (`config/packages/messenger.yaml`), каталог подій —
  [`docs/event-catalog.md`](event-catalog.md).

---

## Карта «сервіс → код»

```
services/user-service/       :8001   БД db-user
services/catalog-service/    :8002   БД db-catalog
services/order-service/      :8003   БД db-order
services/cart-service/       :8004   БД db-cart
services/payment-service/    :8005   БД db-payment
services/delivery-service/   :8006   БД db-delivery
services/notification-service/ :8007 (stateless)
services/storage-service/    :8008   (stateless)
services/export-service/     :8009   БД db-export
src/                                 моноліт: тонкий web/admin-фронт + S2S-клієнти
```

## Пов'язані документи

- [`docs/microservices-analysis.md`](microservices-analysis.md) — аналіз меж (DDD).
- [`docs/microservices-architecture.md`](microservices-architecture.md) — повний дизайн (Task 24).
- [`docs/event-catalog.md`](event-catalog.md) — каталог подій RabbitMQ.
- [`docs/task-26-communication-strategies.md`](task-26-communication-strategies.md) — стратегії комунікації (Task 26).
- [`README.md`](../README.md) — реалізований стан і запуск.

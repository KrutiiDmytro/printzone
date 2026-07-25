# Task 26 — Стратегії комунікації між мікросервісами

> **Звіт-сабмішн для ментора.** Карта «вимога → артефакт»: *що* реалізовано по кожній
> стратегії комунікації і *де саме* в репозиторії. Уся реалізація вже змерджена в `develop`.

**Мета завдання:** дослідити й застосувати різні стратегії комунікації між мікросервісами
для покращення масштабованості, надійності та продуктивності: синхронну, асинхронну,
event-driven і гібридний підхід.

---

## Резюме

У системі співіснують **три стилі комунікації**, скомбіновані в гібридну модель:

| Стиль | Транспорт | Де застосовано |
|---|---|---|
| **Синхронний** | REST/HTTPS + S2S JWT | Читання даних, коли потрібна миттєва відповідь |
| **Асинхронний** | AMQP (RabbitMQ) через Symfony Messenger | Обробка замовлень, оплата, доставка, сповіщення, export |
| **Event-Driven** | Topic exchange (спільна шина подій) | Слабозв'язана реакція сервісів на доменні події |
| **Гібридний** | REST + AMQP в одному потоці | Checkout: миттєва оплата + відкладена обробка |

---

## 1. Синхронна комунікація (REST/HTTPS)

**Вимога:** RESTful HTTP/HTTPS API для прямих синхронних взаємодій, де критична миттєва
відповідь.

**Зроблено.** Сервіс підписує **service-to-service RS256 JWT** спільним Lexik-ключем і
викликає REST-ендпойнти іншого сервісу. Клієнти в моноліті:

```
src/Cart/Client/CartClient.php          → cart-service   (:8004)
src/Catalog/Client/CatalogClient.php    → catalog-service (:8002)
src/Catalog/Client/CatalogAdminClient.php
src/Order/Client/OrderClient.php        → order-service  (:8003)
src/Storage/Client/StorageClient.php    → storage-service (:8008)
src/Export/Client/ExportClient.php      → export-service (:8009)
```

- **Приклад:** Export Service синхронно тягне дані з Catalog/Order/User/Storage
  (CQRS-стиль: читає через read-only API інших сервісів).
- **Незмінний seam:** підміна локального сховища на Storage Service зроблена через незмінний
  інтерфейс `FileStorageInterface` — 6 споживачів не редаговані.
- **Стратегія деградації:** для читань — degrade (показати частковий результат), для
  записів — strict (помилка при недоступності), напр. `CartClient` (read-degrade/write-strict).
- **Чому REST, а не SOAP:** JSON без WSDL-обгортки, нативна підтримка в Symfony/API Platform,
  пряма сумісність з OpenAPI-контрактами (див. Task 27).

## 2. Асинхронна комунікація (message queues)

**Вимога:** черги повідомлень (RabbitMQ/Kafka) для асинхронних процесів (обробка замовлень,
оновлення складу).

**Зроблено.** **RabbitMQ** через **Symfony Messenger**. Конфіг —
[`config/packages/messenger.yaml`](../config/packages/messenger.yaml):

- Транспорт `events` → topic exchange `events`, серіалізація **JSON на дроті** (щоб
  не-PHP-нативний консюмер міг читати).
- Кожен сервіс має власний воркер-контейнер (`*-worker`), що споживає свою чергу.
- **Надійність:** `retry_strategy` (max_retries 3, exponential multiplier) + `failed` transport
  (dead-letter у Doctrine) для повідомлень, що не обробились.
- **Ідемпотентність** консюмерів — захист від повторів at-least-once доставки (webhook/події).
- **Приклади процесів:** обробка замовлення (`order.*`), оновлення складу
  (`StockReserved` / `StockReleased`), async CSV/JSON/XML export через чергу.

> **Чому RabbitMQ, а не Kafka:** для транзакційних бізнес-подій із topic-маршрутизацією й
> помірним обсягом RabbitMQ достатньо; Kafka (event-log, реплей, висока пропускна здатність)
> — надлишкова для поточного навантаження.

## 3. Event-Driven архітектура

**Вимога:** слабозв'язана комунікація через спільну шину подій; сервіси емітять події,
інші підписуються.

**Зроблено.** Спільна шина — **RabbitMQ topic exchange**; повний реєстр подій —
[`docs/event-catalog.md`](event-catalog.md):

- **Домени подій (routing keys):** `user.*`, `catalog.*`, `cart.*`, `order.*`,
  `payment.*`, `delivery.*` — напр. `OrderPaid`, `PaymentSucceeded`, `ShipmentDelivered`,
  `StockReserved`.
- **Слабка зв'язаність:** продюсер не знає консюмерів; сервіс прив'язує свою чергу до
  потрібних binding keys (`order.*`) і реагує незалежно.
- **Outbox Pattern:** подія публікується **в тій самій DB-транзакції**, що й зміна стану
  (order/delivery) → гарантована доставка без втрати подій при збої.
- **Event Sourcing:** immutable `tracking_events` у Delivery Service як джерело істини
  трекінгу.

## 4. Гібридний підхід

**Вимога:** для сценаріїв, що потребують і миттєвої, і відкладеної обробки — поєднати
синхронний API з асинхронним обміном.

**Зроблено — потік Checkout (Choreography Saga):** поєднує синхронний виклик і асинхронні події.

```
[SYNC]  Клієнт → Order Service: створення замовлення (REST)
[SYNC]  Order  → Payment Service / Stripe: ініціація оплати (миттєва відповідь)
        Stripe webhook → Payment Service
[ASYNC] Payment  --PaymentSucceeded-->  Order Service      → статус PAID + подія OrderPaid
[ASYNC] Order    --OrderPaid-------->   Delivery Service   → Shipment + tracking
[ASYNC] Delivery --shipment.*------->   Notification Service → email-квитанція
```

- **Синхронна частина** дає користувачу миттєвий фідбек (створення замовлення, редірект на оплату).
- **Асинхронна частина** розвантажує критичний шлях: доставка, сповіщення, export відбуваються
  поза межами HTTP-запиту, кожен сервіс реагує на події у своєму темпі.
- Це і є гібрид: **REST там, де потрібна миттєвість; події там, де доречна відкладеність.**

---

## Карта «стратегія → артефакт»

| Стратегія | Ключові файли/доки |
|---|---|
| Sync REST + S2S JWT | `src/*/Client/*Client.php`, `config/packages/security.yaml` |
| Async RabbitMQ | `config/packages/messenger.yaml`, `services/*/config/packages/messenger.yaml` |
| Event-driven / bus | [`docs/event-catalog.md`](event-catalog.md) |
| Гібрид (Saga) | Payment/Order/Delivery/Notification сервіси; [`README.md`](../README.md) «Комунікація» |
| Загальний дизайн | [`docs/microservices-architecture.md`](microservices-architecture.md) §5 |

## Пов'язані документи

- [`docs/event-catalog.md`](event-catalog.md) — повний каталог доменних подій.
- [`docs/microservices-architecture.md`](microservices-architecture.md) §5 — міжсервісна комунікація.
- [`docs/task-27-decomposition-report.md`](task-27-decomposition-report.md) — декомпозиція (Task 27).
- [`README.md`](../README.md) — реалізований стан.

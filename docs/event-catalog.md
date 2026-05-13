# Каталог Подій

Всі асинхронні події, якими обмінюються мікросервіси через RabbitMQ.

## Конвенції

- **Тип exchange**: topic
- **Назва exchange**: `{домен}.events` (наприклад, `user.events`, `order.events`)
- **Ключ маршрутизації**: `{домен}.{НазваПодії}` (наприклад, `user.UserLoggedIn`)
- **Формат payload**: JSON
- **Всі події містять**:
  ```json
  {
    "eventId":    "uuid-v4",
    "eventName":  "UserLoggedIn",
    "occurredAt": "2025-05-13T10:00:00Z",
    "traceId":    "uuid-v4",
    "payload":    { ... }
  }
  ```

---

## Події користувача (`user.events`)

### `UserRegistered`
Публікується коли новий користувач завершує реєстрацію.

| Поле | Тип | Опис |
|---|---|---|
| `userId` | UUID | ID нового користувача |
| `email` | string | Email користувача |
| `fullName` | string | Відображуване ім'я |

**Підписники**: Notification Service (привітальний email)

---

### `UserLoggedIn`
Публікується при кожній успішній автентифікації (form login або OAuth).

| Поле | Тип | Опис |
|---|---|---|
| `userId` | UUID | ID автентифікованого користувача |
| `email` | string | Email користувача |
| `sessionId` | string | Ідентифікатор сесії для пошуку гостьового кошика |

**Підписники**: Cart Service (міграція гостьового кошика), Notification Service

---

### `UserUpdated`
Публікується коли профіль користувача змінюється.

| Поле | Тип | Опис |
|---|---|---|
| `userId` | UUID | ID користувача |
| `changedFields` | string[] | Список назв змінених полів |

**Підписники**: Notification Service

---

## Події каталогу (`catalog.events`)

### `ProductCreated`
Публікується коли додається новий продукт.

| Поле | Тип | Опис |
|---|---|---|
| `productId` | UUID | ID нового продукту |
| `name` | string | Назва продукту |
| `price` | integer | Ціна у центах |
| `stock` | integer | Початкова кількість запасів |
| `categoryId` | UUID | ID категорії |

**Підписники**: —

---

### `ProductUpdated`
Публікується коли дані продукту змінюються.

| Поле | Тип | Опис |
|---|---|---|
| `productId` | UUID | ID продукту |
| `changedFields` | string[] | Назви змінених полів (наприклад, `["price","stock"]`) |
| `newPrice` | integer\|null | Нова ціна у центах (якщо ціна змінилась) |

**Підписники**: Cart Service (оновлення знімку ціни при зміні ціни)

---

### `ProductDeleted`
Публікується коли продукт видаляється.

| Поле | Тип | Опис |
|---|---|---|
| `productId` | UUID | ID видаленого продукту |

**Підписники**: Cart Service (видалення позицій з цим продуктом), Notification Service

---

### `StockReserved`
Публікується після успішного резервування запасів для замовлення (крок 2 Checkout Saga).

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID пов'язаного замовлення |
| `items` | array | `[{productId, quantity}]` зарезервовані позиції |

**Підписники**: Order Service (крок саги 3 — перехід до PAYMENT_PENDING)

---

### `StockReservationFailed`
Публікується коли запаси не можуть бути зарезервовані (недостатньо товару).

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID пов'язаного замовлення |
| `productId` | UUID | Продукт, для якого не вдалося зарезервувати |
| `requested` | integer | Запитана кількість |
| `available` | integer | Фактично доступна кількість |
| `reason` | string | Наприклад, `"out_of_stock"` |

**Підписники**: Order Service (компенсація саги — CANCELLED)

---

### `StockReleased`
Публікується коли зарезервовані запаси повертаються (замовлення скасовано після резервування).

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID пов'язаного замовлення |
| `items` | array | `[{productId, quantity}]` звільнені позиції |

**Підписники**: —

---

## Події кошика (`cart.events`)

### `CartCleared`
Публікується коли кошик очищується.

| Поле | Тип | Опис |
|---|---|---|
| `cartId` | UUID | ID кошика |
| `userId` | UUID\|null | ID користувача (null для гостьових кошиків) |

**Підписники**: —

---

### `CartMigrated`
Публікується коли гостьовий сесійний кошик об'єднується з кошиком користувача при вході.

| Поле | Тип | Опис |
|---|---|---|
| `cartId` | UUID | ID результуючого кошика |
| `sessionId` | string | Попередній ID гостьової сесії |
| `userId` | UUID | ID користувача після входу |
| `itemCount` | integer | Кількість мігрованих позицій |

**Підписники**: —

---

## Події замовлень (`order.events`)

### `OrderCreated`
Публікується коли створюється нове замовлення. Запускає Checkout Saga.

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID нового замовлення |
| `userId` | UUID | ID користувача, що замовляє |
| `userEmail` | string | Email користувача (знімок) |
| `items` | array | `[{productId, productName, quantity, price}]` |
| `totalAmount` | integer | Загальна сума у центах |
| `shippingAddress` | object | `{street, city, zip, country}` |

**Підписники**: Catalog Service (резервування запасів), Notification Service (email підтвердження)

---

### `OrderPaid`
Публікується коли оплата підтверджена і замовлення переходить у статус PAID.

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID замовлення |
| `userId` | UUID | ID користувача |
| `paidAt` | ISO 8601 | Мітка часу підтвердження оплати |

**Підписники**: Delivery Service (створення відправлення), Notification Service

---

### `OrderCancelled`
Публікується коли замовлення скасовується (компенсація саги або дія адміністратора).

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID замовлення |
| `userId` | UUID | ID користувача |
| `reason` | string | `"out_of_stock"`, `"payment_failed"`, `"admin_action"` |
| `items` | array | `[{productId, quantity}]` для звільнення запасів |

**Підписники**: Catalog Service (звільнення запасів), Notification Service

---

### `OrderStatusChanged`
Публікується при кожному переході статусу замовлення.

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID замовлення |
| `previousStatus` | string | Попередній статус замовлення |
| `newStatus` | string | Новий статус замовлення |
| `changedAt` | ISO 8601 | Мітка часу переходу |

**Підписники**: Notification Service

---

## Події оплат (`payment.events`)

### `PaymentRequested`
Публікується Order Service для ініціації обробки оплати (крок 3 Checkout Saga).

| Поле | Тип | Опис |
|---|---|---|
| `orderId` | UUID | ID замовлення |
| `paymentId` | UUID | Попередньо виділений ID платежу |
| `amount` | integer | Сума у центах |
| `currency` | string | Код валюти ISO 4217 (наприклад, `"EUR"`) |
| `userId` | UUID | ID користувача для метаданих провайдера |
| `idempotencyKey` | string | Унікальний ключ для дедублювання webhook |

**Підписники**: Payment Service

---

### `PaymentSucceeded`
Публікується коли платіжний провайдер підтверджує успішний платіж.

| Поле | Тип | Опис |
|---|---|---|
| `paymentId` | UUID | ID запису платежу |
| `orderId` | UUID | ID пов'язаного замовлення |
| `amount` | integer | Сума стягнення у центах |
| `currency` | string | Код валюти |
| `providerTxId` | string | Посилання на транзакцію провайдера |

**Підписники**: Order Service (крок саги 5а), Notification Service (email квитанція)

---

### `PaymentFailed`
Публікується коли спроба оплати не вдається.

| Поле | Тип | Опис |
|---|---|---|
| `paymentId` | UUID | ID запису платежу |
| `orderId` | UUID | ID пов'язаного замовлення |
| `reason` | string | Причина відмови від провайдера |
| `providerCode` | string\|null | Код помилки провайдера |

**Підписники**: Order Service (крок саги 5б — CANCELLED), Notification Service

---

### `RefundProcessed`
Публікується коли повернення коштів завершено.

| Поле | Тип | Опис |
|---|---|---|
| `refundId` | UUID | ID запису повернення |
| `paymentId` | UUID | ID оригінального платежу |
| `orderId` | UUID | ID пов'язаного замовлення |
| `amount` | integer | Сума повернення у центах |

**Підписники**: Notification Service

---

## Події доставки (`delivery.events`)

### `ShipmentCreated`
Публікується коли відправлення створено та передано логістичному провайдеру.

| Поле | Тип | Опис |
|---|---|---|
| `shipmentId` | UUID | ID відправлення |
| `orderId` | UUID | ID пов'язаного замовлення |
| `provider` | string | `"nova_poshta"`, `"dhl"` тощо |
| `trackingNumber` | string\|null | Номер відстеження провайдера (null якщо ще не призначено) |

**Підписники**: Notification Service (email + SMS з номером відстеження)

---

### `TrackingUpdated`
Публікується коли записується нова подія відстеження (append-only).

| Поле | Тип | Опис |
|---|---|---|
| `shipmentId` | UUID | ID відправлення |
| `orderId` | UUID | ID пов'язаного замовлення |
| `status` | string | Поточний статус відправлення |
| `location` | string\|null | Опис поточного місцезнаходження |
| `occurredAt` | ISO 8601 | Коли відбулася подія відстеження |

**Підписники**: Notification Service (push-сповіщення)

---

### `ShipmentDelivered`
Публікується коли доставка підтверджена.

| Поле | Тип | Опис |
|---|---|---|
| `shipmentId` | UUID | ID відправлення |
| `orderId` | UUID | ID пов'язаного замовлення |
| `deliveredAt` | ISO 8601 | Мітка часу доставки |

**Підписники**: Notification Service (email підтвердження доставки)

---

## Події експорту (`export.events`)

### `ExportJobCompleted`
Публікується коли асинхронне завдання експорту успішно завершується.

| Поле | Тип | Опис |
|---|---|---|
| `jobId` | UUID | ID завдання експорту |
| `type` | string | `"products"`, `"orders"`, `"users"` |
| `format` | string | `"csv"`, `"json"`, `"xml"` |
| `filePath` | string | Ключ сховища (наприклад, `exports/orders/csv/uuid.csv`) |
| `requestedBy` | string | Email ініціатора |

**Підписники**: Notification Service (email з посиланням для завантаження)

---

### `ExportJobFailed`
Публікується коли асинхронне завдання експорту завершується з помилкою.

| Поле | Тип | Опис |
|---|---|---|
| `jobId` | UUID | ID завдання експорту |
| `type` | string | Тип експорту |
| `format` | string | Формат експорту |
| `errorMessage` | string | Опис помилки |
| `requestedBy` | string | Email ініціатора |

**Підписники**: Notification Service (email з сповіщенням про помилку)

---

## Потік подій: Checkout Saga

```
OrderService      CatalogService     PaymentService    OrderService
    │                   │                  │                │
    │──OrderCreated────►│                  │                │
    │                   │ (резервує запаси)│                │
    │                   │──StockReserved──────────────────►│
    │                   │  АБО             │                │
    │                   │──StockReservationFailed─────────►│
    │                   │                  │                │
    │  (якщо зарезервовано)                │                │
    │                   │    PaymentRequested               │
    │                   │                  │◄───────────────│
    │                   │                  │ (списання)     │
    │                   │                  │                │
    │◄─PaymentSucceeded────────────────────│                │
    │   АБО PaymentFailed                  │                │
    │                   │                  │                │
    │──OrderPaid / OrderCancelled──────────────────────────►│
    │                   │                  │          (наступні кроки)
```

## Потік подій: Після оплати

```
OrderService  DeliveryService  NotificationService
    │               │                  │
    │──OrderPaid───►│                  │
    │               │ (створення відправлення)
    │               │──ShipmentCreated►│ email + SMS
    │               │                  │
    │               │ (оновлення відстеження)
    │               │──TrackingUpdated►│ push-сповіщення
    │               │                  │
    │               │──ShipmentDelivered►│ email
```

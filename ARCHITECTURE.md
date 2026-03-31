# Архітектура E-Commerce додатку на Symfony

## Зміст
1. [Загальна архітектура]
2. [Структура проєкту]
3. [Ключові модулі]
4. [UML діаграми]
5. [Взаємодія модулів]

---

## Загальна архітектура

### Архітектурний підхід
**Модульний моноліт з DDD (Domain-Driven Design)**

### Принципи:
- **Bounded Contexts** - кожен модуль представляє окремий контекст
- **Hexagonal Architecture** - розділення на Domain, Application, Infrastructure
- **Event-Driven** - модулі спілкуються через події (Symfony Messenger)
- **CQRS** - розділення команд і запитів для складних операцій

### Технологічний стек:
- **PHP 8.2+**
- **Symfony 7.0**
- **Doctrine ORM**
- **PostgreSQL** (для всього важливого і постійного(історія замовлень ...))
- **Redis** ( для тимчасового, швидкого (кеш, сесії))
- **Elasticsearch** (пошук)
- **RabbitMQ** (черги повідомлень)
- **API Platform** (REST API)

---

## Структура проєкту

```
electronics-shop/
├── config/                         # Конфігурація Symfony
│   ├── packages/
│   ├── routes/
│   └── services.yaml
│
├── src/
│   ├── Catalog/                    # Модуль каталогу товарів
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── Product.php
│   │   │   │   ├── Category.php
│   │   │   │   └── ProductSpecification.php
│   │   │   ├── Repository/
│   │   │   │   └── ProductRepositoryInterface.php
│   │   │   ├── Service/
│   │   │   │   └── ProductService.php
│   │   │   └── ValueObject/
│   │   │       ├── Price.php
│   │   │       └── SKU.php
│   │   ├── Application/
│   │   │   ├── Command/
│   │   │   │   ├── CreateProductCommand.php
│   │   │   │   └── UpdateProductCommand.php
│   │   │   ├── Query/
│   │   │   │   ├── GetProductQuery.php
│   │   │   │   └── SearchProductsQuery.php
│   │   │   └── Handler/
│   │   │       ├── CreateProductHandler.php
│   │   │       └── GetProductHandler.php
│   │   └── Infrastructure/
│   │       ├── Controller/
│   │       │   └── ProductController.php
│   │       ├── Persistence/
│   │       │   └── DoctrineProductRepository.php
│   │       └── Search/
│   │           └── ElasticsearchProductSearcher.php
│   │
│   ├── User/                       # Модуль користувачів
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── User.php
│   │   │   │   └── Address.php
│   │   │   ├── Repository/
│   │   │   │   └── UserRepositoryInterface.php
│   │   │   └── Service/
│   │   │       └── UserService.php
│   │   ├── Application/
│   │   │   ├── Command/
│   │   │   │   ├── RegisterUserCommand.php
│   │   │   │   └── UpdateProfileCommand.php
│   │   │   └── Handler/
│   │   │       └── RegisterUserHandler.php
│   │   └── Infrastructure/
│   │       ├── Controller/
│   │       │   ├── RegistrationController.php
│   │       │   └── ProfileController.php
│   │       ├── Persistence/
│   │       │   └── DoctrineUserRepository.php
│   │       └── Security/
│   │           └── UserAuthenticator.php
│   │
│   ├── Cart/                       # Модуль кошика
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── Cart.php
│   │   │   │   └── CartItem.php
│   │   │   ├── Repository/
│   │   │   │   └── CartRepositoryInterface.php
│   │   │   └── Service/
│   │   │       └── CartService.php
│   │   ├── Application/
│   │   │   ├── Command/
│   │   │   │   ├── AddToCartCommand.php
│   │   │   │   ├── RemoveFromCartCommand.php
│   │   │   │   └── UpdateCartItemCommand.php
│   │   │   └── Handler/
│   │   │       └── AddToCartHandler.php
│   │   └── Infrastructure/
│   │       ├── Controller/
│   │       │   └── CartController.php
│   │       └── Persistence/
│   │           ├── DoctrineCartRepository.php
│   │           └── RedisCartRepository.php
│   │
│   ├── Order/                      # Модуль замовлень
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── Order.php
│   │   │   │   ├── OrderItem.php
│   │   │   │   └── OrderStatus.php
│   │   │   ├── Event/
│   │   │   │   ├── OrderPlacedEvent.php
│   │   │   │   ├── OrderPaidEvent.php
│   │   │   │   └── OrderShippedEvent.php
│   │   │   ├── Repository/
│   │   │   │   └── OrderRepositoryInterface.php
│   │   │   └── Service/
│   │   │       ├── OrderService.php
│   │   │       └── OrderStateMachine.php
│   │   ├── Application/
│   │   │   ├── Command/
│   │   │   │   ├── PlaceOrderCommand.php
│   │   │   │   └── CancelOrderCommand.php
│   │   │   ├── Handler/
│   │   │   │   └── PlaceOrderHandler.php
│   │   │   └── EventListener/
│   │   │       └── OrderPlacedListener.php
│   │   └── Infrastructure/
│   │       ├── Controller/
│   │       │   └── OrderController.php
│   │       └── Persistence/
│   │           └── DoctrineOrderRepository.php
│   │
│   ├── Payment/                    # Модуль платежів
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── Payment.php
│   │   │   │   └── PaymentMethod.php
│   │   │   ├── Service/
│   │   │   │   └── PaymentGatewayInterface.php
│   │   │   └── Event/
│   │   │       └── PaymentCompletedEvent.php
│   │   ├── Application/
│   │   │   ├── Command/
│   │   │   │   └── ProcessPaymentCommand.php
│   │   │   └── Handler/
│   │   │       └── ProcessPaymentHandler.php
│   │   └── Infrastructure/
│   │       ├── Controller/
│   │       │   └── PaymentController.php
│   │       └── Gateway/
│   │           ├── StripeGateway.php
│   │           └── PayPalGateway.php
│   │
│   ├── Inventory/                  # Модуль складу
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   │   ├── Stock.php
│   │   │   │   └── Reservation.php
│   │   │   ├── Repository/
│   │   │   │   └── StockRepositoryInterface.php
│   │   │   └── Service/
│   │   │       └── InventoryService.php
│   │   ├── Application/
│   │   │   ├── Command/
│   │   │   │   ├── ReserveStockCommand.php
│   │   │   │   └── ReleaseStockCommand.php
│   │   │   └── Handler/
│   │   │       └── ReserveStockHandler.php
│   │   └── Infrastructure/
│   │       ├── Controller/
│   │       │   └── InventoryController.php
│   │       └── Persistence/
│   │           └── DoctrineStockRepository.php
│   │
│   └── Shared/                     # Спільні компоненти
│       ├── Domain/
│       │   ├── Event/
│       │   │   └── DomainEventInterface.php
│       │   └── ValueObject/
│       │       ├── Email.php
│       │       └── Money.php
│       ├── Application/
│       │   └── Service/
│       │       └── EventBus.php
│       └── Infrastructure/
│           ├── Persistence/
│           │   └── DoctrineTypes/
│           └── Messenger/
│               └── EventDispatcher.php
│
├── templates/                      # Twig шаблони
├── public/                         # Публічні файли
├── migrations/                     # Міграції БД
├── tests/                          # Тести
└── var/                           # Тимчасові файли, кеш

```

---

## Ключові модулі

### 1. Catalog (Каталог товарів)

#### Функціональність:
- Управління товарами (CRUD)
- Категорії та підкатегорії
- Характеристики товарів (специфікації для електроніки)
- Пошук та фільтрація
- Управління цінами та знижками

#### Основні сутності:
- **Product** - товар
- **Category** - категорія
- **ProductSpecification** - технічні характеристики
- **Price** (Value Object) - ціна з валютою

#### API ендпоінти:
```
GET    /api/products              # Список товарів
GET    /api/products/{id}         # Деталі товару
POST   /api/products              # Створити товар (admin)
PUT    /api/products/{id}         # Оновити товар (admin)
DELETE /api/products/{id}         # Видалити товар (admin)
GET    /api/categories            # Список категорій
GET    /api/products/search       # Пошук товарів
```

---

### 2. User (Користувачі)

#### Функціональність:
- Реєстрація та аутентифікація
- Управління профілем
- Адреси доставки
- Історія замовлень
- Wishlist (список бажань)

#### Основні сутності:
- **User** - користувач
- **Address** - адреса доставки
- **Email** (Value Object) - email з валідацією

#### API ендпоінти:
```
POST   /api/register              # Реєстрація
POST   /api/login                 # Вхід
GET    /api/profile               # Профіль користувача
PUT    /api/profile               # Оновити профіль
POST   /api/addresses             # Додати адресу
GET    /api/users/{id}/orders     # Історія замовлень
```

---

### 3. Cart (Кошик)

#### Функціональність:
- Додавання товарів до кошика
- Зміна кількості
- Видалення товарів
- Розрахунок підсумкової суми
- Застосування промокодів

#### Основні сутності:
- **Cart** - кошик
- **CartItem** - елемент кошика

#### Зберігання:
- **Redis** - для гостей (сесія)
- **PostgreSQL** - для авторизованих користувачів

#### API ендпоінти:
```
GET    /api/cart                  # Отримати кошик
POST   /api/cart/items            # Додати товар
PUT    /api/cart/items/{id}       # Оновити кількість
DELETE /api/cart/items/{id}       # Видалити товар
DELETE /api/cart                  # Очистити кошик
POST   /api/cart/apply-coupon     # Застосувати промокод
```

---

### 4. Order (Замовлення)

#### Функціональність:
- Оформлення замовлення
- Управління статусами замовлення
- Історія замовлень
- Скасування замовлення
- Повернення

#### Основні сутності:
- **Order** - замовлення
- **OrderItem** - елемент замовлення
- **OrderStatus** - статус замовлення (enum)

#### Статуси замовлення:
1. `PENDING` - очікує оплати
2. `PAID` - оплачено
3. `PROCESSING` - в обробці
4. `SHIPPED` - відправлено
5. `DELIVERED` - доставлено
6. `CANCELLED` - скасовано
7. `REFUNDED` - повернено

#### Події:
- `OrderPlacedEvent` - замовлення створено
- `OrderPaidEvent` - замовлення оплачено
- `OrderShippedEvent` - замовлення відправлено
- `OrderCancelledEvent` - замовлення скасовано

#### API ендпоінти:
```
POST   /api/orders                # Створити замовлення
GET    /api/orders/{id}           # Деталі замовлення
GET    /api/orders                # Список замовлень
PUT    /api/orders/{id}/cancel    # Скасувати замовлення
GET    /api/orders/{id}/status    # Статус замовлення
```

---

### 5. Payment (Платежі)

#### Функціональність:
- Обробка платежів
- Інтеграція з платіжними системами
- Повернення коштів
- Історія транзакцій

#### Основні сутності:
- **Payment** - платіж
- **PaymentMethod** - спосіб оплати
- **Transaction** - транзакція

#### Підтримувані платіжні системи:
- Stripe
- PayPal
- Банківські картки

#### Події:
- `PaymentCompletedEvent` - платіж завершено
- `PaymentFailedEvent` - платіж не пройшов
- `RefundProcessedEvent` - повернення оброблено

#### API ендпоінти:
```
POST   /api/payments              # Створити платіж
GET    /api/payments/{id}         # Статус платежу
POST   /api/payments/{id}/refund  # Повернення коштів
GET    /api/payment-methods       # Доступні способи оплати
```

---

### 6. Inventory (Склад)

#### Функціональність:
- Управління залишками
- Резервування товарів
- Звільнення резервів
- Сповіщення про низькі залишки

#### Основні сутності:
- **Stock** - залишок товару
- **Reservation** - резервування

#### Події:
- `StockReservedEvent` - товар зарезервовано
- `StockReleasedEvent` - резерв звільнено
- `LowStockEvent` - низький залишок

#### API ендпоінти:
```
GET    /api/inventory/{productId} # Залишок товару
POST   /api/inventory/reserve     # Зарезервувати
POST   /api/inventory/release     # Звільнити резерв
PUT    /api/inventory/{productId} # Оновити залишок (admin)
```

---

## UML Діаграми

Детальні діаграми (ER, Sequence, State Machine, Layered Architecture) винесені в окремий файл для зручності: [UML_DIAGRAMS.md](UML_DIAGRAMS.md)

В цьому файлі ви знайдете:
1. **Концептуальну модель даних (ER-діаграма)**
2. **Процес оформлення замовлення (Sequence Diagram)**
3. **Життєвий цикл замовлення (State Diagram)**
4. **Шарувату архітектуру (Layered Architecture)**


## Взаємодія модулів

### Event-Driven Communication

Модулі взаємодіють через події (Symfony Messenger):

```
Order Module                 Inventory Module
     │                             │
     │──OrderPlacedEvent──────────>│
     │                             │──reserveStock()
     │                             │
     │<──StockReservedEvent────────│
     │                             │
     
Order Module                 Payment Module
     │                             │
     │──OrderPlacedEvent──────────>│
     │                             │──processPayment()
     │                             │
     │<──PaymentCompletedEvent─────│
     │                             │
     │──markAsPaid()               │
     │                             │
     
Order Module                 Notification Module
     │                             │
     │──OrderPaidEvent────────────>│
     │                             │──sendConfirmation()
     │                             │
```

### Синхронна взаємодія

Деякі операції вимагають синхронної взаємодії:

```php
// CartService використовує ProductService
class CartService
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CartRepositoryInterface $cartRepository
    ) {}
    
    public function addItem(int $productId, int $quantity): void
    {
        // Синхронна перевірка наявності товару
        $product = $this->productRepository->find($productId);
        
        if (!$product->isAvailable()) {
            throw new ProductNotAvailableException();
        }
        
        // Додавання до кошика
        $cart = $this->cartRepository->findOrCreate();
        $cart->addItem($product, $quantity);
        $this->cartRepository->save($cart);
    }
}
```

---

## Патерни та практики

### 1. Repository Pattern
```php
interface ProductRepositoryInterface
{
    public function find(int $id): ?Product;
    public function findByCategory(int $categoryId): array;
    public function save(Product $product): void;
    public function remove(Product $product): void;
}
```

### 2. Command/Query Separation (CQRS)
```php
// Command - змінює стан
class CreateProductCommand
{
    public function __construct(
        public readonly string $name,
        public readonly float $price,
        public readonly int $categoryId
    ) {}
}

// Query - тільки читання
class GetProductQuery
{
    public function __construct(
        public readonly int $id
    ) {}
}
```

### 3. Domain Events
```php
class OrderPlacedEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly array $items,
        public readonly float $totalAmount
    ) {}
}
```

### 4. Value Objects
```php
class Money
{
    public function __construct(
        private float $amount,
        private string $currency = 'EUR'
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Ціна не може бути від\'ємною');
        }
    }
    
    public function getAmount(): float
    {
        return $this->amount;
    }
    
    public function getCurrency(): string
    {
        return $this->currency;
    }
}
```

---

## Безпека

### Аутентифікація та авторизація
- JWT токени для API
- Session-based для веб-інтерфейсу
- Role-based access control (RBAC)

### Ролі:
- `ROLE_USER` - звичайний користувач
- `ROLE_ADMIN` - адміністратор
- `ROLE_MANAGER` - менеджер магазину

### Захист даних:
- Хешування паролів (bcrypt)
- HTTPS обов'язковий
- CSRF захист
- Rate limiting для API

---

## Продуктивність

### Кешування:
- **Redis** - сесії, кошики гостей, результати пошуку
- **HTTP Cache** - статичні сторінки каталогу
- **Doctrine Query Cache** - результати запитів

### Оптимізація БД:
- Індекси на часто використовуваних полях
- Eager loading для пов'язаних сутностей
- Database query optimization

### Асинхронна обробка:
- Відправка email через черги
- Оновлення пошукового індексу
- Генерація звітів

---

## Масштабування

### Горизонтальне масштабування:
- Stateless додаток
- Shared nothing architecture
- Load balancer (Nginx)

### Вертикальне масштабування:
- Збільшення ресурсів сервера
- Оптимізація PHP (OPcache)
- Database tuning

### Мікросервіси (майбутнє):
Модулі, які можна виділити першими:
1. Payment Service (вимагає ізоляції)
2. Catalog Service (потрібне незалежне масштабування)
3. Search Service (Elasticsearch)
4. Notification Service (асинхронна обробка)

---

## Тестування

### Типи тестів:
- **Unit Tests** - тестування доменної логіки
- **Integration Tests** - тестування з БД
- **Functional Tests** - тестування API endpoints
- **E2E Tests** - повний цикл покупки

### Інструменти:
- PHPUnit
- Symfony Functional Tests
- Behat (BDD)
- PHPStan (статичний аналіз)
- SonarQube (аналіз якості коду та безпеки)

---

## Моніторинг та логування

### Логування:
- Monolog для структурованих логів
- ELK Stack (Elasticsearch, Logstash, Beats, Kibana)
- Логування всіх критичних операцій

### Моніторинг:
- Application Performance Monitoring (APM)
- Database query monitoring
- Error tracking (Sentry)
- Business metrics (замовлення, виручка)

---

## Висновок

Ця архітектура забезпечує:
- ✅ Модульність та підтримуваність
- ✅ Масштабованість
- ✅ Тестованість
- ✅ Гнучкість для майбутніх змін
- ✅ Відповідність кращим практикам Symfony та DDD

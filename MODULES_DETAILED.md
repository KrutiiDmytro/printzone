# Детальное описание модулей E-Commerce приложения

## Оглавление
1. [Catalog Module (Каталог)](#catalog-module)
2. [User Module (Пользователи)](#user-module)
3. [Cart Module (Корзина)](#cart-module)
4. [Order Module (Заказы)](#order-module)
5. [Payment Module (Платежи)](#payment-module)
6. [Inventory Module (Склад)](#inventory-module)
7. [Notification Module (Уведомления)](#notification-module)
8. [Review Module (Отзывы)](#review-module)

---

## Catalog Module

### Назначение
Управление каталогом товаров электроники, категориями, характеристиками и ценами.

### Основные сущности

#### 1. Product (Товар)
```php
namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ValueObject\Price;
use App\Catalog\Domain\ValueObject\SKU;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ApiResource]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', unique: true)]
    private SKU $sku;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Embedded(class: Price::class)]
    private Price $price;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    private Category $category;

    #[ORM\Column]
    private int $stock = 0;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductSpecification::class)]
    private Collection $specifications;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    public function __construct(
        SKU $sku,
        string $name,
        string $description,
        Price $price,
        Category $category
    ) {
        $this->sku = $sku;
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->category = $category;
        $this->specifications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function updatePrice(Price $newPrice): void
    {
        $this->price = $newPrice;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isAvailable(): bool
    {
        return $this->isActive && $this->stock > 0;
    }

    public function addSpecification(string $name, string $value): void
    {
        $specification = new ProductSpecification($this, $name, $value);
        $this->specifications->add($specification);
    }

    // Getters...
    public function getId(): ?int { return $this->id; }
    public function getSku(): SKU { return $this->sku; }
    public function getName(): string { return $this->name; }
    public function getPrice(): Price { return $this->price; }
    public function getStock(): int { return $this->stock; }
}
```

#### 2. Category (Категория)
```php
namespace App\Catalog\Domain\Entity;

#[ORM\Entity]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $slug;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    private ?Category $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
    private Collection $children;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Product::class)]
    private Collection $products;

    public function __construct(string $name, string $slug, ?Category $parent = null)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->parent = $parent;
        $this->children = new ArrayCollection();
        $this->products = new ArrayCollection();
    }

    public function addChild(Category $child): void
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->parent = $this;
        }
    }

    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function isRoot(): bool
    {
        return $this->parent === null;
    }

    // Getters...
}
```

#### 3. ProductSpecification (Характеристики)
```php
namespace App\Catalog\Domain\Entity;

#[ORM\Entity]
class ProductSpecification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'specifications')]
    private Product $product;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $value;

    public function __construct(Product $product, string $name, string $value)
    {
        $this->product = $product;
        $this->name = $name;
        $this->value = $value;
    }

    // Getters...
}
```

### Value Objects

#### Price
```php
namespace App\Catalog\Domain\ValueObject;

#[ORM\Embeddable]
class Price
{
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $amount;

    #[ORM\Column(length: 3)]
    private string $currency;

    public function __construct(float $amount, string $currency = 'USD')
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Price cannot be negative');
        }
        
        $this->amount = $amount;
        $this->currency = strtoupper($currency);
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function equals(Price $other): bool
    {
        return $this->amount === $other->amount 
            && $this->currency === $other->currency;
    }

    public function __toString(): string
    {
        return sprintf('%.2f %s', $this->amount, $this->currency);
    }
}
```

#### SKU
```php
namespace App\Catalog\Domain\ValueObject;

class SKU
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('SKU cannot be empty');
        }
        
        if (!preg_match('/^[A-Z0-9\-]+$/', $value)) {
            throw new \InvalidArgumentException('Invalid SKU format');
        }
        
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### Application Layer

#### Commands
```php
namespace App\Catalog\Application\Command;

class CreateProductCommand
{
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly string $description,
        public readonly float $price,
        public readonly string $currency,
        public readonly int $categoryId,
        public readonly int $stock = 0,
        public readonly array $specifications = []
    ) {}
}

class UpdateProductCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?float $price = null,
        public readonly ?int $stock = null
    ) {}
}
```

#### Queries
```php
namespace App\Catalog\Application\Query;

class GetProductQuery
{
    public function __construct(
        public readonly int $id
    ) {}
}

class SearchProductsQuery
{
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?int $categoryId = null,
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly int $page = 1,
        public readonly int $limit = 20
    ) {}
}
```

#### Handlers
```php
namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Command\CreateProductCommand;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Domain\Repository\CategoryRepositoryInterface;
use App\Catalog\Domain\ValueObject\Price;
use App\Catalog\Domain\ValueObject\SKU;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CreateProductHandler
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function __invoke(CreateProductCommand $command): Product
    {
        $category = $this->categoryRepository->find($command->categoryId);
        
        if (!$category) {
            throw new \DomainException('Category not found');
        }

        $product = new Product(
            new SKU($command->sku),
            $command->name,
            $command->description,
            new Price($command->price, $command->currency),
            $category
        );

        foreach ($command->specifications as $name => $value) {
            $product->addSpecification($name, $value);
        }

        $this->productRepository->save($product);

        return $product;
    }
}
```

### Infrastructure Layer

#### Controller
```php
namespace App\Catalog\Infrastructure\Controller;

use App\Catalog\Application\Command\CreateProductCommand;
use App\Catalog\Application\Query\GetProductQuery;
use App\Catalog\Application\Query\SearchProductsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/products')]
class ProductController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private MessageBusInterface $queryBus
    ) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $query = new SearchProductsQuery(
            query: $request->query->get('q'),
            categoryId: $request->query->getInt('category'),
            minPrice: $request->query->get('min_price'),
            maxPrice: $request->query->get('max_price'),
            page: $request->query->getInt('page', 1),
            limit: $request->query->getInt('limit', 20)
        );

        $envelope = $this->queryBus->dispatch($query);
        $products = $envelope->last(HandledStamp::class)->getResult();

        return $this->json($products);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        $query = new GetProductQuery($id);
        $envelope = $this->queryBus->dispatch($query);
        $product = $envelope->last(HandledStamp::class)->getResult();

        return $this->json($product);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $command = new CreateProductCommand(
            sku: $data['sku'],
            name: $data['name'],
            description: $data['description'],
            price: $data['price'],
            currency: $data['currency'] ?? 'USD',
            categoryId: $data['category_id'],
            stock: $data['stock'] ?? 0,
            specifications: $data['specifications'] ?? []
        );

        $envelope = $this->commandBus->dispatch($command);
        $product = $envelope->last(HandledStamp::class)->getResult();

        return $this->json($product, 201);
    }
}
```

#### Repository
```php
namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function find(int $id): ?Product
    {
        return parent::find($id);
    }

    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.category = :categoryId')
            ->andWhere('p.isActive = true')
            ->setParameter('categoryId', $categoryId)
            ->getQuery()
            ->getResult();
    }

    public function save(Product $product): void
    {
        $this->getEntityManager()->persist($product);
        $this->getEntityManager()->flush();
    }

    public function remove(Product $product): void
    {
        $this->getEntityManager()->remove($product);
        $this->getEntityManager()->flush();
    }
}
```

### Взаимодействие с другими модулями

#### С Cart Module
- Cart запрашивает информацию о товаре при добавлении
- Проверяет наличие товара (isAvailable)

#### С Order Module
- Order использует информацию о товаре при создании заказа
- Сохраняет snapshot цены на момент заказа

#### С Inventory Module
- Синхронизация остатков товаров
- Обновление stock при резервировании

---

## User Module

### Назначение
Управление пользователями, аутентификация, профили и адреса доставки.

### Основные сущности

#### 1. User (Пользователь)
```php
namespace App\User\Domain\Entity;

use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

#[ORM\Entity]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', unique: true)]
    private Email $email;

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(type: 'json')]
    private array $roles = ['ROLE_USER'];

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Address::class)]
    private Collection $addresses;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Order::class)]
    private Collection $orders;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private bool $isVerified = false;

    public function __construct(
        Email $email,
        string $firstName,
        string $lastName
    ) {
        $this->email = $email;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->addresses = new ArrayCollection();
        $this->orders = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }

    public function addAddress(Address $address): void
    {
        if (!$this->addresses->contains($address)) {
            $this->addresses->add($address);
        }
    }

    public function getDefaultAddress(): ?Address
    {
        foreach ($this->addresses as $address) {
            if ($address->isDefault()) {
                return $address;
            }
        }
        return null;
    }

    // UserInterface implementation
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getUserIdentifier(): string
    {
        return $this->email->getValue();
    }

    public function eraseCredentials(): void
    {
        // Nothing to do
    }

    // Getters and setters...
}
```

#### 2. Address (Адрес)
```php
namespace App\User\Domain\Entity;

#[ORM\Entity]
class Address
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'addresses')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $street;

    #[ORM\Column(length: 100)]
    private string $city;

    #[ORM\Column(length: 20)]
    private string $postalCode;

    #[ORM\Column(length: 100)]
    private string $country;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private bool $isDefault = false;

    public function __construct(
        User $user,
        string $street,
        string $city,
        string $postalCode,
        string $country,
        ?string $phone = null
    ) {
        $this->user = $user;
        $this->street = $street;
        $this->city = $city;
        $this->postalCode = $postalCode;
        $this->country = $country;
        $this->phone = $phone;
    }

    public function getFullAddress(): string
    {
        return sprintf(
            '%s, %s, %s, %s',
            $this->street,
            $this->city,
            $this->postalCode,
            $this->country
        );
    }

    public function setAsDefault(): void
    {
        // Сначала убираем default у всех других адресов пользователя
        foreach ($this->user->getAddresses() as $address) {
            if ($address !== $this) {
                $address->isDefault = false;
            }
        }
        
        $this->isDefault = true;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    // Getters...
}
```

### Application Layer

#### Commands
```php
namespace App\User\Application\Command;

class RegisterUserCommand
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $firstName,
        public readonly string $lastName
    ) {}
}

class UpdateProfileCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null
    ) {}
}

class AddAddressCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly string $street,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly ?string $phone = null,
        public readonly bool $isDefault = false
    ) {}
}
```

#### Handlers
```php
namespace App\User\Application\Handler;

use App\User\Application\Command\RegisterUserCommand;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler]
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function __invoke(RegisterUserCommand $command): User
    {
        // Проверяем, не существует ли уже пользователь
        $existingUser = $this->userRepository->findByEmail($command->email);
        if ($existingUser) {
            throw new \DomainException('User with this email already exists');
        }

        $user = new User(
            new Email($command->email),
            $command->firstName,
            $command->lastName
        );

        // Хешируем пароль
        $hashedPassword = $this->passwordHasher->hashPassword($user, $command->password);
        $user->setPassword($hashedPassword);

        $this->userRepository->save($user);

        return $user;
    }
}
```

### Infrastructure Layer

#### Security
```php
namespace App\User\Infrastructure\Security;

use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class UserAuthenticator extends AbstractLoginFormAuthenticator
{
    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($password)
        );
    }

    // ... другие методы
}
```

---

## Cart Module

### Назначение
Управление корзиной покупок, добавление/удаление товаров, расчет итоговой суммы.

### Основные сущности

#### 1. Cart (Корзина)
```php
namespace App\Cart\Domain\Entity;

use App\Shared\Domain\ValueObject\Money;

#[ORM\Entity]
class Cart
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\OneToMany(mappedBy: 'cart', targetEntity: CartItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(?User $user = null, ?string $sessionId = null)
    {
        $this->user = $user;
        $this->sessionId = $sessionId;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addItem(Product $product, int $quantity): void
    {
        // Проверяем, есть ли уже такой товар
        foreach ($this->items as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $item->increaseQuantity($quantity);
                $this->updatedAt = new \DateTimeImmutable();
                return;
            }
        }

        // Если нет, создаем новый
        $item = new CartItem($this, $product, $quantity);
        $this->items->add($item);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function removeItem(CartItem $item): void
    {
        $this->items->removeElement($item);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($item);
            return;
        }

        $item->setQuantity($quantity);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getTotal(): Money
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal()->getAmount();
        }

        return new Money($total, 'USD');
    }

    public function clear(): void
    {
        $this->items->clear();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->getQuantity();
        }
        return $count;
    }

    // Getters...
}
```

#### 2. CartItem (Элемент корзины)
```php
namespace App\Cart\Domain\Entity;

use App\Shared\Domain\ValueObject\Money;

#[ORM\Entity]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    private Cart $cart;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Embedded(class: Price::class)]
    private Price $price;

    public function __construct(Cart $cart, Product $product, int $quantity)
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }

        $this->cart = $cart;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->price = $product->getPrice(); // Snapshot цены
    }

    public function getSubtotal(): Money
    {
        return new Money(
            $this->price->getAmount() * $this->quantity,
            $this->price->getCurrency()
        );
    }

    public function increaseQuantity(int $amount): void
    {
        $this->quantity += $amount;
    }

    public function decreaseQuantity(int $amount): void
    {
        $this->quantity -= $amount;
        
        if ($this->quantity <= 0) {
            throw new \DomainException('Quantity cannot be zero or negative');
        }
    }

    public function setQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be positive');
        }
        
        $this->quantity = $quantity;
    }

    // Getters...
}
```

### Application Layer

#### Commands
```php
namespace App\Cart\Application\Command;

class AddToCartCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly ?int $userId = null,
        public readonly ?string $sessionId = null
    ) {}
}

class RemoveFromCartCommand
{
    public function __construct(
        public readonly int $cartItemId,
        public readonly ?int $userId = null,
        public readonly ?string $sessionId = null
    ) {}
}

class UpdateCartItemCommand
{
    public function __construct(
        public readonly int $cartItemId,
        public readonly int $quantity,
        public readonly ?int $userId = null,
        public readonly ?string $sessionId = null
    ) {}
}

class ClearCartCommand
{
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $sessionId = null
    ) {}
}
```

#### Service
```php
namespace App\Cart\Domain\Service;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;

class CartService
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductRepositoryInterface $productRepository
    ) {}

    public function addToCart(
        int $productId,
        int $quantity,
        ?int $userId = null,
        ?string $sessionId = null
    ): Cart {
        $product = $this->productRepository->find($productId);
        
        if (!$product) {
            throw new \DomainException('Product not found');
        }

        if (!$product->isAvailable()) {
            throw new \DomainException('Product is not available');
        }

        if ($product->getStock() < $quantity) {
            throw new \DomainException('Not enough stock');
        }

        $cart = $this->cartRepository->findOrCreate($userId, $sessionId);
        $cart->addItem($product, $quantity);
        
        $this->cartRepository->save($cart);

        return $cart;
    }

    public function getCart(?int $userId = null, ?string $sessionId = null): ?Cart
    {
        return $this->cartRepository->findOrCreate($userId, $sessionId);
    }

    public function mergeGuestCart(string $sessionId, int $userId): void
    {
        $guestCart = $this->cartRepository->findBySession($sessionId);
        
        if (!$guestCart || $guestCart->isEmpty()) {
            return;
        }

        $userCart = $this->cartRepository->findOrCreateForUser($userId);

        foreach ($guestCart->getItems() as $item) {
            $userCart->addItem($item->getProduct(), $item->getQuantity());
        }

        $this->cartRepository->save($userCart);
        $this->cartRepository->remove($guestCart);
    }
}
```

### Взаимодействие с другими модулями

#### С Catalog Module
- Получает информацию о товаре
- Проверяет наличие и остатки

#### С Order Module
- Передает содержимое корзины при оформлении заказа
- Очищается после успешного создания заказа

#### С User Module
- Связывает корзину с пользователем после авторизации
- Объединяет гостевую корзину с пользовательской

---

## Order Module

### Назначение
Управление заказами, их статусами, обработка жизненного цикла заказа.

### Основные сущности

#### 1. Order (Заказ)
```php
namespace App\Order\Domain\Entity;

use App\Shared\Domain\ValueObject\Money;

#[ORM\Entity]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $orderNumber;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'orders')]
    private User $user;

    #[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderItem::class, cascade: ['persist'])]
    private Collection $items;

    #[ORM\Column(type: 'string', enumType: OrderStatus::class)]
    private OrderStatus $status;

    #[ORM\Embedded(class: Money::class)]
    private Money $totalAmount;

    #[ORM\Embedded(class: Address::class)]
    private Address $shippingAddress;

    #[ORM\OneToOne(targetEntity: Payment::class, mappedBy: 'order')]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    public function __construct(
        User $user,
        Address $shippingAddress
    ) {
        $this->user = $user;
        $this->shippingAddress = $shippingAddress;
        $this->orderNumber = $this->generateOrderNumber();
        $this->status = OrderStatus::PENDING;
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function createFromCart(Cart $cart, Address $shippingAddress): self
    {
        $order = new self($cart->getUser(), $shippingAddress);

        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem(
                $order,
                $cartItem->getProduct(),
                $cartItem->getQuantity(),
                $cartItem->getPrice()
            );
            $order->items->add($orderItem);
        }

        $order->calculateTotal();

        return $order;
    }

    public function place(): void
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new \DomainException('Order can only be placed from PENDING status');
        }

        // Заказ уже создан, просто меняем статус если нужно
        // В данном случае оставляем PENDING до оплаты
    }

    public function markAsPaid(): void
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new \DomainException('Only pending orders can be marked as paid');
        }

        $this->status = OrderStatus::PAID;
        $this->paidAt = new \DateTimeImmutable();
    }

    public function startProcessing(): void
    {
        if ($this->status !== OrderStatus::PAID) {
            throw new \DomainException('Only paid orders can be processed');
        }

        $this->status = OrderStatus::PROCESSING;
    }

    public function ship(): void
    {
        if ($this->status !== OrderStatus::PROCESSING) {
            throw new \DomainException('Only processing orders can be shipped');
        }

        $this->status = OrderStatus::SHIPPED;
        $this->shippedAt = new \DateTimeImmutable();
    }

    public function deliver(): void
    {
        if ($this->status !== OrderStatus::SHIPPED) {
            throw new \DomainException('Only shipped orders can be delivered');
        }

        $this->status = OrderStatus::DELIVERED;
        $this->deliveredAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::DELIVERED) {
            throw new \DomainException('Delivered orders cannot be cancelled');
        }

        $this->status = OrderStatus::CANCELLED;
    }

    public function refund(): void
    {
        if ($this->status !== OrderStatus::PAID && $this->status !== OrderStatus::DELIVERED) {
            throw new \DomainException('Only paid or delivered orders can be refunded');
        }

        $this->status = OrderStatus::REFUNDED;
    }

    private function calculateTotal(): void
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal()->getAmount();
        }

        $this->totalAmount = new Money($total, 'USD');
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
    }

    public function getTotal(): Money
    {
        return $this->totalAmount;
    }

    // Getters...
}
```

#### 2. OrderStatus (Enum)
```php
namespace App\Order\Domain\Entity;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';

    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return match ($this) {
            self::PENDING => in_array($newStatus, [self::PAID, self::CANCELLED]),
            self::PAID => in_array($newStatus, [self::PROCESSING, self::REFUNDED, self::CANCELLED]),
            self::PROCESSING => in_array($newStatus, [self::SHIPPED, self::CANCELLED]),
            self::SHIPPED => in_array($newStatus, [self::DELIVERED]),
            self::DELIVERED => in_array($newStatus, [self::REFUNDED]),
            default => false,
        };
    }
}
```

#### 3. OrderItem
```php
namespace App\Order\Domain\Entity;

#[ORM\Entity]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column]
    private int $quantity;

    #[ORM\Embedded(class: Price::class)]
    private Price $price;

    #[ORM\Column(length: 255)]
    private string $productName; // Snapshot

    #[ORM\Column(length: 50)]
    private string $productSku; // Snapshot

    public function __construct(
        Order $order,
        Product $product,
        int $quantity,
        Price $price
    ) {
        $this->order = $order;
        $this->product = $product;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->productName = $product->getName();
        $this->productSku = $product->getSku()->getValue();
    }

    public function getSubtotal(): Money
    {
        return new Money(
            $this->price->getAmount() * $this->quantity,
            $this->price->getCurrency()
        );
    }

    // Getters...
}
```

### Domain Events

```php
namespace App\Order\Domain\Event;

class OrderPlacedEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly array $items,
        public readonly float $totalAmount
    ) {}
}

class OrderPaidEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly int $userId,
        public readonly float $amount
    ) {}
}

class OrderShippedEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $trackingNumber
    ) {}
}

class OrderCancelledEvent
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $reason
    ) {}
}
```

### Application Layer

#### Commands
```php
namespace App\Order\Application\Command;

class PlaceOrderCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly int $addressId,
        public readonly string $paymentMethod
    ) {}
}

class CancelOrderCommand
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $reason
    ) {}
}
```

#### Handlers
```php
namespace App\Order\Application\Handler;

use App\Order\Application\Command\PlaceOrderCommand;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Event\OrderPlacedEvent;
use App\Cart\Domain\Service\CartService;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class PlaceOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private UserRepositoryInterface $userRepository,
        private CartService $cartService,
        private MessageBusInterface $eventBus
    ) {}

    public function __invoke(PlaceOrderCommand $command): Order
    {
        $user = $this->userRepository->find($command->userId);
        $cart = $this->cartService->getCart($command->userId);

        if ($cart->isEmpty()) {
            throw new \DomainException('Cart is empty');
        }

        $address = $user->getAddressById($command->addressId);
        
        if (!$address) {
            throw new \DomainException('Address not found');
        }

        // Создаем заказ из корзины
        $order = Order::createFromCart($cart, $address);
        
        $this->orderRepository->save($order);

        // Отправляем событие
        $this->eventBus->dispatch(new OrderPlacedEvent(
            $order->getId(),
            $user->getId(),
            $this->extractItems($order),
            $order->getTotal()->getAmount()
        ));

        // Очищаем корзину
        $cart->clear();

        return $order;
    }

    private function extractItems(Order $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'product_id' => $item->getProduct()->getId(),
                'quantity' => $item->getQuantity(),
            ];
        }
        return $items;
    }
}
```

#### Event Listeners
```php
namespace App\Order\Application\EventListener;

use App\Order\Domain\Event\OrderPlacedEvent;
use App\Inventory\Application\Command\ReserveStockCommand;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class OrderPlacedListener
{
    public function __construct(
        private MessageBusInterface $commandBus
    ) {}

    public function __invoke(OrderPlacedEvent $event): void
    {
        // Резервируем товары на складе
        foreach ($event->items as $item) {
            $this->commandBus->dispatch(new ReserveStockCommand(
                $item['product_id'],
                $item['quantity'],
                $event->orderId
            ));
        }
    }
}
```

---

Документ получился очень большим. Продолжить с остальными модулями (Payment, Inventory, Notification, Review)?

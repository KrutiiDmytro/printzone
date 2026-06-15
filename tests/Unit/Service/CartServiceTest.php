<?php

namespace App\Tests\Unit\Service;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Category;
use App\Catalog\Domain\Entity\Product;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use App\Service\CartService;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Uid\Uuid;

class CartServiceTest extends TestCase
{
    private CartService $cartService;
    private $requestStack;
    private $productRepository;
    private $cartRepository;
    private $entityManager;
    private $security;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->productRepository = $this->createMock(ProductRepository::class);
        $this->cartRepository = $this->createMock(CartRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);

        $this->cartService = new CartService(
            $this->requestStack,
            $this->productRepository,
            $this->cartRepository,
            $this->entityManager,
            $this->security
        );
    }

    private function makeProduct(Uuid $id, string $name, int $price, int $stock = 0): Product
    {
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName($name);
        $product->setPrice($price);
        $product->setStock($stock);
        $product->setCategory($category);

        $reflection = new \ReflectionClass($product);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($product, $id);

        return $product;
    }

    public function testRemoveFromSession(): void
    {
        $keep = (string) Uuid::v4();
        $drop = (string) Uuid::v4();
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [$drop => 2, $keep => 1]);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->remove($drop);

        $cart = $session->get('cart', []);
        $this->assertArrayNotHasKey($drop, $cart);
        $this->assertArrayHasKey($keep, $cart);
    }

    public function testUpdateInSession(): void
    {
        $id = (string) Uuid::v4();
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [$id => 2]);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->update($id, 5);

        $cart = $session->get('cart', []);
        $this->assertEquals(5, $cart[$id]);
    }

    public function testUpdateInSessionRemovesItemWhenQuantityIsZero(): void
    {
        $id = (string) Uuid::v4();
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [$id => 2]);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->update($id, 0);

        $cart = $session->get('cart', []);
        $this->assertArrayNotHasKey($id, $cart);
    }

    public function testClearSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [(string) Uuid::v4() => 2, (string) Uuid::v4() => 1]);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->clear();

        $this->assertNull($session->get('cart'));
    }

    public function testAddToDatabaseCreatesNewCartIfNotExists(): void
    {
        $productId = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(Uuid::v4());

        $product = $this->makeProduct($productId, 'Product', 10000);

        $this->security->method('getUser')->willReturn($user);
        $this->cartRepository->method('findOneByUserId')->willReturn(null);
        $this->productRepository->method('find')->with($productId)->willReturn($product);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $this->cartService->add((string) $productId, 2);
    }

    public function testAddToDatabaseUpdatesExistingItem(): void
    {
        $productId = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(Uuid::v4());

        $product = $this->makeProduct($productId, 'Product', 10000);

        $cart = new Cart();
        $cart->setUserId(Uuid::v4());

        $existingItem = new CartItem();
        $existingItem->setProductId($productId);
        $existingItem->setProductName('Product');
        $existingItem->setPrice(10000);
        $existingItem->setQuantity(2);
        $cart->addItem($existingItem);

        $this->security->method('getUser')->willReturn($user);
        $this->cartRepository->method('findOneByUserId')->willReturn($cart);
        $this->productRepository->method('find')->with($productId)->willReturn($product);

        $this->entityManager->expects($this->once())->method('flush');

        $this->cartService->add((string) $productId, 3);

        $this->assertEquals(5, $existingItem->getQuantity());
    }

    public function testMigrateSessionToDatabase(): void
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(Uuid::v4());
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [(string) $id1 => 2, (string) $id2 => 3]);

        $product1 = $this->makeProduct($id1, 'Product 1', 10000);
        $product2 = $this->makeProduct($id2, 'Product 2', 5000);

        $cart = new Cart();
        $cart->setUserId(Uuid::v4());

        $this->security->method('getUser')->willReturn($user);
        $this->requestStack->method('getSession')->willReturn($session);

        $this->cartRepository->method('findOneByUserId')
            ->willReturnOnConsecutiveCalls(null, $cart);

        $this->productRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => [(string) $id1, (string) $id2]])
            ->willReturn([$product1, $product2]);

        // persist: 1× (create cart); flush: 3× (create cart + 2× addProductToDatabase)
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->exactly(3))->method('flush');

        $this->cartService->migrateSessionToDatabase();

        $this->assertNull($session->get('cart'));
    }

    public function testGetCountReturnsCorrectCount(): void
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [(string) $id1 => 2, (string) $id2 => 1]);

        $product1 = $this->makeProduct($id1, 'Product 1', 10000, 10);
        $product2 = $this->makeProduct($id2, 'Product 2', 5000, 5);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);
        $this->productRepository->method('findBy')
            ->with(['id' => [(string) $id1, (string) $id2]])
            ->willReturn([$product1, $product2]);

        $this->assertEquals(2, $this->cartService->getCount());
    }
}

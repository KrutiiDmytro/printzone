<?php

namespace App\Tests\Unit\Service;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Domain\Entity\Category;
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

    public function testRemoveFromSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [1 => 2, 2 => 1]);
        
        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->remove(1);

        $cart = $session->get('cart', []);
        $this->assertArrayNotHasKey(1, $cart);
        $this->assertArrayHasKey(2, $cart);
    }

    public function testUpdateInSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [1 => 2]);
        
        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->update(1, 5);

        $cart = $session->get('cart', []);
        $this->assertEquals(5, $cart[1]);
    }

    public function testUpdateInSessionRemovesItemWhenQuantityIsZero(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [1 => 2]);
        
        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->update(1, 0);

        $cart = $session->get('cart', []);
        $this->assertArrayNotHasKey(1, $cart);
    }

    public function testClearSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [1 => 2, 2 => 1]);
        
        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->clear();

        $this->assertNull($session->get('cart'));
    }

    public function testAddToDatabaseCreatesNewCartIfNotExists(): void
    {
        $user = $this->createMock(User::class);
        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product = new Product();
        $product->setName('Product');
        $product->setPrice(10000);
        $product->setCategory($category);

        $this->security->method('getUser')->willReturn($user);
        $this->cartRepository->method('findOneByUser')->willReturn(null);
        $this->productRepository->method('find')->with(1)->willReturn($product);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->exactly(2))->method('flush');

        $this->cartService->add(1, 2);
    }

    public function testAddToDatabaseUpdatesExistingItem(): void
{
    $user = $this->createMock(User::class);
    $category = new Category();
    $category->setName('Test Category');
    $category->setSlug('test-category');

    $product = new Product();
    $product->setName('Product');
    $product->setPrice(10000);
    $product->setCategory($category);
    
    // Устанавливаем ID продукта через Reflection
    $reflection = new \ReflectionClass($product);
    $idProperty = $reflection->getProperty('id');
    $idProperty->setAccessible(true);
    $idProperty->setValue($product, 1);

    $cart = new Cart();
    $cart->setUser($user);

    $existingItem = new CartItem();
    $existingItem->setProduct($product);
    $existingItem->setQuantity(2);
    $cart->addItem($existingItem);

    $this->security->method('getUser')->willReturn($user);
    $this->cartRepository->method('findOneByUser')->willReturn($cart);
    $this->productRepository->method('find')->with(1)->willReturn($product);

    $this->entityManager->expects($this->once())->method('flush');

    $this->cartService->add(1, 3);

    $this->assertEquals(5, $existingItem->getQuantity());
}

    public function testMigrateSessionToDatabase(): void
    {
        $user = $this->createMock(User::class);
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [1 => 2, 2 => 3]);

        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->setPrice(10000);
        $product1->setCategory($category);

        $reflection1 = new \ReflectionClass($product1);
        $idProperty1 = $reflection1->getProperty('id');
        $idProperty1->setAccessible(true);
        $idProperty1->setValue($product1, 1);

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->setPrice(5000);
        $product2->setCategory($category);

        $reflection2 = new \ReflectionClass($product2);
        $idProperty2 = $reflection2->getProperty('id');
        $idProperty2->setAccessible(true);
        $idProperty2->setValue($product2, 2);

        $cart = new Cart();
        $cart->setUser($user);

        $this->security->method('getUser')->willReturn($user);
        $this->requestStack->method('getSession')->willReturn($session);

        // Products are batch-fetched once via findBy; findOneByUser called twice (once per item)
        $this->cartRepository->method('findOneByUser')
            ->willReturnOnConsecutiveCalls(null, $cart);

        $this->productRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => [1, 2]])
            ->willReturn([$product1, $product2]);

        // persist: 1× (create cart); flush: 3× (create cart + 2× addProductToDatabase)
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->exactly(3))->method('flush');

        $this->cartService->migrateSessionToDatabase();

        $this->assertNull($session->get('cart'));
    }
    public function testGetCountReturnsCorrectCount(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [1 => 2, 2 => 1]);

        $category = new Category();
        $category->setName('Test Category');
        $category->setSlug('test-category');

        $product1 = new Product();
        $product1->setName('Product 1');
        $product1->setPrice(10000);
        $product1->setStock(10);
        $product1->setCategory($category);

        $reflection1 = new \ReflectionClass($product1);
        $idProp1 = $reflection1->getProperty('id');
        $idProp1->setAccessible(true);
        $idProp1->setValue($product1, 1);

        $product2 = new Product();
        $product2->setName('Product 2');
        $product2->setPrice(5000);
        $product2->setStock(5);
        $product2->setCategory($category);

        $reflection2 = new \ReflectionClass($product2);
        $idProp2 = $reflection2->getProperty('id');
        $idProp2->setAccessible(true);
        $idProp2->setValue($product2, 2);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);
        $this->productRepository->method('findBy')
            ->with(['id' => [1, 2]])
            ->willReturn([$product1, $product2]);

        $this->assertEquals(2, $this->cartService->getCount());
    }
}
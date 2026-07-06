<?php

namespace App\Tests\Unit\Service;

use App\Cart\Client\CartClient;
use App\Catalog\Client\CatalogClient;
use App\Catalog\View\ProductView;
use App\Service\CartService;
use App\User\Domain\Entity\User;
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
    private $catalog;
    private $cartClient;
    private $security;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->catalog = $this->createMock(CatalogClient::class);
        $this->cartClient = $this->createMock(CartClient::class);
        $this->security = $this->createMock(Security::class);

        $this->cartService = new CartService(
            $this->requestStack,
            $this->catalog,
            $this->cartClient,
            $this->security
        );
    }

    private function makeProduct(Uuid $id, string $name, int $price, int $stock = 0): ProductView
    {
        return ProductView::fromArray([
            'id' => (string) $id,
            'name' => $name,
            'price' => $price,
            'stock' => $stock,
            'category' => ['id' => (string) Uuid::v4(), 'name' => 'Test Category', 'slug' => 'test-category'],
        ]);
    }

    private function makeUser(Uuid $id): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }

    // === Session (guest) ===

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

    public function testClearSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [(string) Uuid::v4() => 2, (string) Uuid::v4() => 1]);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);

        $this->cartService->clear();

        $this->assertNull($session->get('cart'));
    }

    public function testGetCountReturnsSessionCount(): void
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [(string) $id1 => 2, (string) $id2 => 1]);

        $this->requestStack->method('getSession')->willReturn($session);
        $this->security->method('getUser')->willReturn(null);
        $this->catalog->method('productsByIds')
            ->with([(string) $id1, (string) $id2])
            ->willReturn([
                (string) $id1 => $this->makeProduct($id1, 'Product 1', 10000, 10),
                (string) $id2 => $this->makeProduct($id2, 'Product 2', 5000, 5),
            ]);

        $this->assertEquals(2, $this->cartService->getCount());
    }

    // === Cart Service (authenticated) ===

    public function testAddResolvesProductAndDelegatesToCartClient(): void
    {
        $userId = Uuid::v4();
        $productId = Uuid::v4();
        $user = $this->makeUser($userId);
        $product = $this->makeProduct($productId, 'Ink', 10000);

        $this->security->method('getUser')->willReturn($user);
        $this->catalog->method('product')->with((string) $productId)->willReturn($product);

        $this->cartClient->expects($this->once())
            ->method('addItem')
            ->with((string) $userId, (string) $productId, 'Ink', 10000, 2);

        $this->cartService->add((string) $productId, 2);
    }

    public function testAddSkipsWhenProductMissing(): void
    {
        $user = $this->makeUser(Uuid::v4());

        $this->security->method('getUser')->willReturn($user);
        $this->catalog->method('product')->willReturn(null);

        $this->cartClient->expects($this->never())->method('addItem');

        $this->cartService->add((string) Uuid::v4(), 1);
    }

    public function testRemoveDelegatesToCartClient(): void
    {
        $userId = Uuid::v4();
        $productId = (string) Uuid::v4();
        $this->security->method('getUser')->willReturn($this->makeUser($userId));

        $this->cartClient->expects($this->once())->method('removeItem')->with((string) $userId, $productId);

        $this->cartService->remove($productId);
    }

    public function testUpdateDelegatesToCartClient(): void
    {
        $userId = Uuid::v4();
        $productId = (string) Uuid::v4();
        $this->security->method('getUser')->willReturn($this->makeUser($userId));

        $this->cartClient->expects($this->once())->method('updateItem')->with((string) $userId, $productId, 4);

        $this->cartService->update($productId, 4);
    }

    public function testClearDelegatesToCartClient(): void
    {
        $userId = Uuid::v4();
        $this->security->method('getUser')->willReturn($this->makeUser($userId));

        $this->cartClient->expects($this->once())->method('clear')->with((string) $userId);

        $this->cartService->clear();
    }

    public function testGetCartEnrichesServiceLinesWithFreshCatalog(): void
    {
        $userId = Uuid::v4();
        $productId = Uuid::v4();
        $user = $this->makeUser($userId);

        $this->security->method('getUser')->willReturn($user);
        $this->cartClient->method('get')->with((string) $userId)->willReturn([
            ['productId' => (string) $productId, 'productName' => 'Snapshot', 'price' => 1000, 'quantity' => 3],
        ]);
        $this->catalog->method('productsByIds')
            ->with([(string) $productId])
            ->willReturn([(string) $productId => $this->makeProduct($productId, 'Fresh', 1000, 50)]);

        $cart = $this->cartService->getCart();

        $this->assertCount(1, $cart['items']);
        $this->assertSame(3000, $cart['total']);
        $this->assertSame(1, $cart['count']);
        $this->assertSame('Fresh', $cart['items'][0]['product']->getName());
    }

    public function testGetCartSkipsOrphanedLines(): void
    {
        $userId = Uuid::v4();
        $productId = Uuid::v4();
        $this->security->method('getUser')->willReturn($this->makeUser($userId));
        $this->cartClient->method('get')->willReturn([
            ['productId' => (string) $productId, 'productName' => 'Gone', 'price' => 1000, 'quantity' => 1],
        ]);
        // Catalog no longer has the product → orphaned line is dropped.
        $this->catalog->method('productsByIds')->willReturn([]);

        $cart = $this->cartService->getCart();

        $this->assertSame([], $cart['items']);
        $this->assertSame(0, $cart['total']);
    }

    public function testMigrateSessionToDatabaseAddsEachProduct(): void
    {
        $id1 = Uuid::v4();
        $id2 = Uuid::v4();
        $userId = Uuid::v4();
        $user = $this->makeUser($userId);
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [(string) $id1 => 2, (string) $id2 => 3]);

        $this->security->method('getUser')->willReturn($user);
        $this->requestStack->method('getSession')->willReturn($session);
        $this->catalog->expects($this->once())
            ->method('productsByIds')
            ->with([(string) $id1, (string) $id2])
            ->willReturn([
                (string) $id1 => $this->makeProduct($id1, 'Product 1', 10000),
                (string) $id2 => $this->makeProduct($id2, 'Product 2', 5000),
            ]);

        $matcher = $this->exactly(2);
        $this->cartClient->expects($matcher)->method('addItem');

        $this->cartService->migrateSessionToDatabase();

        $this->assertNull($session->get('cart'));
    }
}

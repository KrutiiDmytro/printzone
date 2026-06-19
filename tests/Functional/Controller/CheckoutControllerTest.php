<?php

namespace App\Tests\Functional\Controller;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Client\CatalogClient;
use App\Catalog\View\ProductView;
use App\Messaging\Domain\Entity\OutboxMessage;
use App\Payment\Service\StripeCheckoutService;
use App\Tests\Functional\WebTestCase;
use App\User\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;

class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutRequiresAuthentication(): void
    {
        $client = static::createClient();

        // Створюємо схему після створення клієнта
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/checkout');

        $this->assertResponseRedirects('/login');
    }

    public function testCheckoutIsAccessibleForAuthenticatedUser(): void
    {
        [$client] = $this->authWithCartProduct('Checkout Test Product', 1000, 10);

        $client->request('GET', '/checkout');

        $this->assertResponseIsSuccessful();
        // Використовуємо більш точний селектор, оскільки h1 може бути в логотипі "Electro"
        $this->assertAnySelectorTextContains('h1', 'Billing details');
    }

    public function testCheckoutRedirectsWhenCartIsEmpty(): void
    {
        $client = $this->createUserClient();

        // Перевіряємо, що при порожній корзині користувач перенаправляється на сторінку кошика
        $client->request('GET', '/checkout');

        // Перевіряємо редирект на /cart
        $this->assertResponseRedirects('/cart');

        // Можна також перевірити flash-повідомлення після редиректу
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testPlaceOrderRedirectsToStripe(): void
    {
        $stripeUrl = 'https://checkout.stripe.com/c/pay/cs_test_123';
        $mock = $this->createMock(StripeCheckoutService::class);
        $mock->method('createSession')->willReturn(['id' => 'cs_test_123', 'url' => $stripeUrl]);

        [$client, $container] = $this->authWithCartProduct('Stripe Test Product', 2000, 5, $mock);

        $client->request('POST', '/checkout/place-order');

        $this->assertResponseRedirects($stripeUrl);
        // Saga: OrderCreated emitted into the outbox in the same transaction.
        $this->assertSame(['OrderCreated'], $this->outboxEventNames($container));
    }

    public function testPlaceOrderHandlesStripeFailure(): void
    {
        $mock = $this->createMock(StripeCheckoutService::class);
        $mock->method('createSession')->willThrowException(new \RuntimeException('Stripe API error'));

        [$client, $container] = $this->authWithCartProduct('Stripe Fail Product', 1500, 5, $mock);

        $client->request('POST', '/checkout/place-order');

        // Saga: HELD then released — both OrderCreated and OrderCancelled emitted.
        $this->assertEqualsCanonicalizing(['OrderCreated', 'OrderCancelled'], $this->outboxEventNames($container));

        $this->assertResponseRedirects('/checkout');
    }

    /**
     * Sets up an authenticated client with a single-product cart, mocking the
     * Catalog client (and optionally Stripe) BEFORE login so the test container
     * can replace them before they are first used.
     *
     * @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: object}
     */
    private function authWithCartProduct(string $name, int $price, int $stock, ?StripeCheckoutService $stripeMock = null): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $container = static::getContainer();
        // Replace the Catalog client before anything can initialise it.
        $this->mockCatalog($container);
        if (null !== $stripeMock) {
            $container->set(StripeCheckoutService::class, $stripeMock);
        }

        $this->createSchema();
        $this->loadFixtures();

        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        // Catalog products live in catalog-service; the cart item only needs an id snapshot.
        $this->persistCartWithItem($em, $user, Uuid::v4(), $name, $price, 1);

        $client->loginUser($user, 'main');

        return [$client, $container];
    }

    /**
     * Event names recorded in the transactional outbox, in insertion order.
     *
     * @return list<string>
     */
    private function outboxEventNames(object $container): array
    {
        $em = $container->get('doctrine.orm.entity_manager');
        $rows = $em->getRepository(OutboxMessage::class)->findBy([], ['createdAt' => 'ASC']);

        return array_map(static fn (OutboxMessage $m): string => $m->getEventName(), $rows);
    }

    /**
     * Generic Catalog stub: returns a product view for any requested id so the
     * cart resolves its snapshot lines, and empty nav lists.
     */
    private function mockCatalog(object $container): void
    {
        $view = static fn (string $id): ProductView => ProductView::fromArray([
            'id' => $id, 'name' => 'Catalog Product', 'price' => 1000, 'stock' => 100,
        ]);

        $mock = $this->createMock(CatalogClient::class);
        $mock->method('product')->willReturnCallback($view);
        $mock->method('productsByIds')->willReturnCallback(
            static function (array $ids) use ($view): array {
                $map = [];
                foreach ($ids as $id) {
                    $map[(string) $id] = $view((string) $id);
                }

                return $map;
            }
        );
        $mock->method('brands')->willReturn([]);
        $mock->method('rootCategories')->willReturn([]);

        $container->set(CatalogClient::class, $mock);
    }

    private function persistCartWithItem(object $entityManager, User $user, Uuid $productId, string $name, int $price, int $quantity): void
    {
        $cart = new Cart();
        $cart->setUserId($user->getId());
        $entityManager->persist($cart);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setProductId($productId);
        $cartItem->setProductName($name);
        $cartItem->setPrice($price);
        $cartItem->setQuantity($quantity);
        $cart->addItem($cartItem);
        $entityManager->persist($cartItem);

        $entityManager->flush();
    }
}

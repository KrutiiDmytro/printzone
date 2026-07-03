<?php

namespace App\Tests\Functional\Controller;

use App\Cart\Client\CartClient;
use App\Catalog\Client\CatalogClient;
use App\Catalog\View\ProductView;
use App\Order\Client\OrderClient;
use App\Tests\Functional\WebTestCase;
use App\User\Domain\Entity\User;
use Symfony\Component\Uid\Uuid;

class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutRequiresAuthentication(): void
    {
        $client = static::createClient();

        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/checkout');

        $this->assertResponseRedirects('/login');
    }

    public function testCheckoutIsAccessibleForAuthenticatedUser(): void
    {
        [$client] = $this->authWithCart([$this->line('Checkout Test Product', 1000)]);

        $client->request('GET', '/checkout');

        $this->assertResponseIsSuccessful();
        $this->assertAnySelectorTextContains('h1', 'Billing details');
    }

    public function testCheckoutRedirectsWhenCartIsEmpty(): void
    {
        [$client] = $this->authWithCart([]);

        $client->request('GET', '/checkout');

        $this->assertResponseRedirects('/cart');

        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testPlaceOrderRedirectsToStripe(): void
    {
        $stripeUrl = 'https://checkout.stripe.com/c/pay/cs_test_123';
        $mock = $this->createMock(OrderClient::class);
        $mock->method('createCheckout')->willReturn(['orderId' => (string) Uuid::v4(), 'url' => $stripeUrl]);

        [$client] = $this->authWithCart([$this->line('Stripe Test Product', 2000)], $mock);

        $client->request('POST', '/checkout/place-order');

        // Order creation + Stripe session now live in order-service; the monolith
        // just delegates and redirects the user to the returned payment URL.
        $this->assertResponseRedirects($stripeUrl);
    }

    public function testPlaceOrderHandlesOrderServiceFailure(): void
    {
        $mock = $this->createMock(OrderClient::class);
        $mock->method('createCheckout')->willThrowException(new \RuntimeException('Order Service unavailable'));

        [$client] = $this->authWithCart([$this->line('Stripe Fail Product', 1500)], $mock);

        $client->request('POST', '/checkout/place-order');

        // Failure is surfaced as a flash and the user is sent back to checkout.
        $this->assertResponseRedirects('/checkout');
    }

    /**
     * @return array{productId: string, productName: string, price: int, quantity: int}
     */
    private function line(string $name, int $price, int $quantity = 1): array
    {
        return ['productId' => (string) Uuid::v4(), 'productName' => $name, 'price' => $price, 'quantity' => $quantity];
    }

    /**
     * Authenticates a user whose cart (owned by cart-service) is stubbed to the
     * given snapshot lines. Mocks Catalog + Cart clients BEFORE login so the test
     * container replaces them before they are first used.
     *
     * @param list<array{productId: string, productName: string, price: int, quantity: int}> $items
     *
     * @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: object}
     */
    private function authWithCart(array $items, ?OrderClient $orderMock = null): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $container = static::getContainer();
        $this->mockCatalog($container);
        $this->mockCart($container, $items);
        if (null !== $orderMock) {
            $container->set(OrderClient::class, $orderMock);
        }

        $this->createSchema();
        $this->loadFixtures();

        $em = $container->get('doctrine.orm.entity_manager');
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $client->loginUser($user, 'main');

        return [$client, $container];
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

    /**
     * @param list<array{productId: string, productName: string, price: int, quantity: int}> $items
     */
    private function mockCart(object $container, array $items): void
    {
        $mock = $this->createMock(CartClient::class);
        $mock->method('get')->willReturn($items);

        $container->set(CartClient::class, $mock);
    }
}

<?php

namespace App\Tests\Functional\Controller;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Client\CatalogClient;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\View\ProductView;
use App\Payment\Service\StripeCheckoutService;
use App\Tests\Functional\WebTestCase;
use App\User\Domain\Entity\User;

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

        [$client] = $this->authWithCartProduct('Stripe Test Product', 2000, 5, $mock);

        $client->request('POST', '/checkout/place-order');

        $this->assertResponseRedirects($stripeUrl);
    }

    public function testPlaceOrderHandlesStripeFailure(): void
    {
        $mock = $this->createMock(StripeCheckoutService::class);
        $mock->method('createSession')->willThrowException(new \RuntimeException('Stripe API error'));

        [$client] = $this->authWithCartProduct('Stripe Fail Product', 1500, 5, $mock);

        $client->request('POST', '/checkout/place-order');

        $this->assertResponseRedirects('/checkout');
    }

    /**
     * Sets up an authenticated client with a single-product cart, mocking the
     * Catalog client (and optionally Stripe) BEFORE login so the test container
     * can replace them before they are first used.
     *
     * @return array{0: \Symfony\Bundle\FrameworkBundle\KernelBrowser, 1: object, 2: Product}
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
        $product = $this->persistProduct($em, $name, $price, $stock);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $this->persistCartWithItem($em, $user, $product, 1);

        $client->loginUser($user, 'main');

        return [$client, $container, $product];
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

    private function persistProduct(object $entityManager, string $name, int $price, int $stock): Product
    {
        $product = new Product();
        $product->setName($name);
        $product->setDescription('Test Description');
        $product->setPrice($price);
        $product->setStock($stock);

        $category = $entityManager->getRepository(\App\Catalog\Domain\Entity\Category::class)->findOneBy([]);
        if ($category) {
            $product->setCategory($category);
        }

        $entityManager->persist($product);
        $entityManager->flush(); // flush so the product gets an id for the cart-item snapshot

        return $product;
    }

    private function persistCartWithItem(object $entityManager, User $user, Product $product, int $quantity): void
    {
        $cart = new Cart();
        $cart->setUserId($user->getId());
        $entityManager->persist($cart);

        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setProductId($product->getId());
        $cartItem->setProductName($product->getName());
        $cartItem->setPrice($product->getPrice());
        $cartItem->setQuantity($quantity);
        $cart->addItem($cartItem);
        $entityManager->persist($cartItem);

        $entityManager->flush();
    }
}

<?php

namespace App\Tests\Functional\Controller;

use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Product;
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
        $client = $this->createUserClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $product = $this->persistProduct($entityManager, 'Checkout Test Product', 1000, 10);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $this->persistCartWithItem($entityManager, $user, $product, 1);

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
        $client = $this->createUserClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $product = $this->persistProduct($entityManager, 'Stripe Test Product', 2000, 5);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $this->persistCartWithItem($entityManager, $user, $product, 1);

        $stripeUrl = 'https://checkout.stripe.com/c/pay/cs_test_123';

        $mock = $this->createMock(StripeCheckoutService::class);
        $mock->method('createSession')->willReturn(['id' => 'cs_test_123', 'url' => $stripeUrl]);
        $container->set(StripeCheckoutService::class, $mock);

        $client->request('POST', '/checkout/place-order');

        $this->assertResponseRedirects($stripeUrl);
    }

    public function testPlaceOrderHandlesStripeFailure(): void
    {
        $client = $this->createUserClient();
        $container = static::getContainer();
        $entityManager = $container->get('doctrine.orm.entity_manager');

        $product = $this->persistProduct($entityManager, 'Stripe Fail Product', 1500, 5);
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => 'user@example.com']);
        $this->persistCartWithItem($entityManager, $user, $product, 1);

        $mock = $this->createMock(StripeCheckoutService::class);
        $mock->method('createSession')->willThrowException(new \RuntimeException('Stripe API error'));
        $container->set(StripeCheckoutService::class, $mock);

        $client->request('POST', '/checkout/place-order');

        $this->assertResponseRedirects('/checkout');
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

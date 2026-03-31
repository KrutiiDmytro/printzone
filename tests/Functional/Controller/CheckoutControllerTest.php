<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\WebTestCase;
use App\Cart\Domain\Entity\Cart;
use App\Cart\Domain\Entity\CartItem;
use App\Catalog\Domain\Entity\Product;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\Response;

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

        // Створюємо продукт
        $product = new Product();
        $product->setName('Checkout Test Product');
        $product->setDescription('Test Description');
        $product->setPrice(1000);
        $product->setStock(10);
        
        $categoryRepository = $entityManager->getRepository(\App\Catalog\Domain\Entity\Category::class);
        $category = $categoryRepository->findOneBy([]);
        if ($category) {
            $product->setCategory($category);
        }
        $entityManager->persist($product);

        // Отримуємо користувача
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'user@example.com']);

        // Створюємо кошик
        $cart = new Cart();
        $cart->setUser($user);
        $entityManager->persist($cart);

        // Додаємо товар до кошика
        $cartItem = new CartItem();
        $cartItem->setCart($cart);
        $cartItem->setProduct($product);
        $cartItem->setQuantity(1);
        $cart->addItem($cartItem); // Explicitly add to collection
        $entityManager->persist($cartItem);
        
        $entityManager->flush();
        
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
}
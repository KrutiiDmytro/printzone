<?php

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\WebTestCase;

class SecurityTest extends WebTestCase
{
    public function testAdminLoginPageIsAccessible(): void
    {
        $client = static::createClient();

        // Створюємо схему після створення клієнта
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/admin/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Sign In');
    }

    public function testAdminDashboardRequiresAuthentication(): void
    {
        $client = static::createClient();

        // Створюємо схему після створення клієнта
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminDashboardRequiresAdminRole(): void
    {
        // Сама создаст клиента и подготовит БД
        $client = $this->createUserClient();

        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminDashboardAccessibleForAdmin(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Панель управления');
    }

    public function testAdminLoginWithValidCredentials(): void
    {
        $client = static::createClient();

        // Створюємо схему після створення клієнта
        $this->createSchema();
        $this->loadFixtures();

        $crawler = $client->request('GET', '/admin/login');

        $form = $crawler->selectButton('Sign In')->form([
            '_username' => 'admin@example.com',
            '_password' => 'admin123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/admin');
    }

    public function testAdminLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        // Створюємо схему після створення клієнта
        $this->createSchema();
        $this->loadFixtures();

        $crawler = $client->request('GET', '/admin/login');

        $form = $crawler->selectButton('Sign In')->form([
            '_username' => 'admin@example.com',
            '_password' => 'wrongpassword',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/admin/login');
    }

    public function testRegularUserCannotAccessAdminRoutes(): void
    {
        // Автентифікуємо користувача для брандмауера 'main'
        $client = $this->createUserClient();

        // Product/Category/Brand admin moved to catalog-service (step 5.3) and
        // orders to order-service (/admin/orders proxy); the admin firewall still
        // guards the dashboard, the EasyAdmin user CRUD and the order proxy.
        $adminRoutes = [
            '/admin',
            '/admin/user',
            '/admin/orders',
        ];

        foreach ($adminRoutes as $route) {
            $client->request('GET', $route);
            // Очікуємо редирект на /admin/login, оскільки користувач не автентифікований для брандмауера 'admin'
            $this->assertResponseRedirects(
                '/admin/login',
                null,
                sprintf('Route %s should redirect to login for users from main firewall', $route)
            );
        }
    }
}

<?php

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class ProductCrudTest extends WebTestCase
{
    public function testProductListIsAccessibleForAdmin(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin/product');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Управление товарами');
    }

    public function testProductListRequiresAdminRole(): void
    {

        
        // Автентифікуємо користувача для брандмауера 'main'
        $client = $this->createUserClient();
        
        $client->request('GET', '/admin/product');
        
        // Очікуємо редирект на /admin/login, оскільки користувач не автентифікований для брандмауера 'admin'
        $this->assertResponseRedirects('/admin/login');
    }

    public function testCreateProduct(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/admin/product/new');

        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Сохранить')->form([
            'Product[name]' => 'Test Product',
            'Product[description]' => 'Test Description',
            'Product[price]' => 9999, // в центах
            'Product[stock]' => 10,
            'Product[isFeatured]' => false,
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/admin/product');
    }

    public function testEditProduct(): void
    {
        $client = $this->createAdminClient();
        
        // Спочатку отримуємо список продуктів
        $crawler = $client->request('GET', '/admin/product');
        $this->assertResponseIsSuccessful();

        // Знаходимо перший продукт і переходимо на редагування
        $editLink = $crawler->filter('.action-edit')->first();
        
        if ($editLink->count() > 0) {
            $client->clickLink($editLink->text());
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('h1', 'Редактировать товар');
        }
    }

    public function testProductFilters(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/admin/product');

        $this->assertResponseIsSuccessful();
        
        // Перевіряємо наявність фільтрів
        $this->assertSelectorExists('form[method="get"]');
    }
}
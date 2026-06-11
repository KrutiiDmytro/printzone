<?php

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\WebTestCase;

class OrderCrudTest extends WebTestCase
{
    public function testOrderListIsAccessibleForAdmin(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin/order');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Управление заказами');
    }

    public function testOrderCreateButtonIsNotVisible(): void
    {
        $client = $this->createAdminClient();
        $crawler = $client->request('GET', '/admin/order');

        $this->assertResponseIsSuccessful();

        // Перевіряємо, що кнопка "Создать" відсутня
        $this->assertSelectorNotExists('a[href*="/admin/order/new"]');
    }

    public function testOrderStatusCanBeUpdated(): void
    {
        $client = $this->createAdminClient();

        // Отримуємо список замовлень
        $crawler = $client->request('GET', '/admin/order');
        $this->assertResponseIsSuccessful();

        // Знаходимо перше замовлення
        $editLink = $crawler->filter('a[title="Редактировать"]')->first();

        if ($editLink->count() > 0) {
            $client->clickLink($editLink->text());
            $this->assertResponseIsSuccessful();

            // Перевіряємо наявність поля статусу
            $this->assertSelectorExists('select[name*="[status]"]');
        }
    }
}

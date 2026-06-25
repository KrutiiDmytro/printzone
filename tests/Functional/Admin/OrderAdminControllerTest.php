<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Order\Client\OrderClient;
use App\Tests\Functional\WebTestCase;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

class OrderAdminControllerTest extends WebTestCase
{
    public function testOrdersRequireAdminLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/admin/orders');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testOrdersListRendersForAdmin(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('list')->willReturn([
            ['id' => (string) Uuid::v4(), 'userEmail' => 'buyer@example.com', 'status' => 'PAID',
                'totalAmount' => 4242, 'createdAt' => '2026-01-15T10:30:00+00:00'],
        ]);

        $client->request('GET', '/admin/orders');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'buyer@example.com');
    }

    public function testDetailRendersItemsAndStatusForm(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $id = (string) Uuid::v4();
        $mock->method('find')->willReturn([
            'id' => $id, 'userEmail' => 'buyer@example.com', 'status' => 'PAID', 'totalAmount' => 4242,
            'createdAt' => '2026-01-15T10:30:00+00:00',
            'items' => [['productId' => (string) Uuid::v4(), 'productName' => 'Probe Toner', 'price' => 4242, 'quantity' => 1]],
        ]);

        $client->request('GET', '/admin/orders/'.$id);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Probe Toner');
        $this->assertSelectorExists('select[name="status"]');
    }

    public function testUpdateStatusPostsToService(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $id = (string) Uuid::v4();
        $mock->method('find')->willReturn([
            'id' => $id, 'userEmail' => 'buyer@example.com', 'status' => 'PAID', 'totalAmount' => 4242,
            'createdAt' => '2026-01-15T10:30:00+00:00', 'items' => [],
        ]);
        $mock->expects(self::once())->method('updateStatus')->with($id, 'SHIPPED')->willReturn(['id' => $id]);

        $crawler = $client->request('GET', '/admin/orders/'.$id);
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/orders/'.$id.'/status', ['_token' => $token, 'status' => 'SHIPPED']);

        $this->assertResponseRedirects('/admin/orders/'.$id);
    }

    /**
     * @return array{0: KernelBrowser, 1: OrderClient&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function adminClientWithMock(): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $container = static::getContainer();
        $mock = $this->createMock(OrderClient::class);
        $container->set(OrderClient::class, $mock);

        $this->createSchema();
        $this->loadFixtures();

        $admin = $container->get('doctrine')->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $client->loginUser($admin, 'admin');

        return [$client, $mock];
    }
}

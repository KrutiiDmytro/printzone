<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Catalog\Client\CatalogAdminClient;
use App\Tests\Functional\WebTestCase;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Uid\Uuid;

class CatalogAdminControllerTest extends WebTestCase
{
    public function testProductsRequireAdminLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/admin/catalog/products');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testProductsListRendersForAdmin(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('products')->willReturn([
            ['id' => (string) Uuid::v4(), 'name' => 'Probe Toner', 'price' => 4242, 'stock' => 5,
                'isFeatured' => true, 'category' => ['name' => 'Toner'], 'brand' => ['name' => 'HP']],
        ]);

        $client->request('GET', '/admin/catalog/products');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Probe Toner');
    }

    public function testCreateProductPostsToService(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $catId = (string) Uuid::v4();
        $mock->method('products')->willReturn([]);
        $mock->method('categories')->willReturn([['id' => $catId, 'name' => 'Ink']]);
        $mock->method('brands')->willReturn([]);
        $mock->expects(self::once())->method('create')
            ->with('products', self::callback(static fn (array $d): bool => 'New One' === $d['name']
                && 1500 === $d['price'] && $catId === $d['category']))
            ->willReturn(['id' => (string) Uuid::v4()]);

        $crawler = $client->request('GET', '/admin/catalog/products/new');
        $token = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/catalog/products/new', [
            '_token' => $token,
            'name' => 'New One',
            'price' => '15.00',
            'stock' => '3',
            'category' => $catId,
        ]);

        $this->assertResponseRedirects('/admin/catalog/products');
    }

    public function testDeleteBrandPostsToService(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $brandId = (string) Uuid::v4();
        $mock->method('brands')->willReturn([['id' => $brandId, 'name' => 'Dropme', 'slug' => 'dropme', 'color' => null]]);
        $mock->expects(self::once())->method('delete')->with('brands', $brandId);

        $crawler = $client->request('GET', '/admin/catalog/brands');
        $token = $crawler->filter('input[name="_token"]')->first()->attr('value');

        $client->request('POST', '/admin/catalog/brands/'.$brandId.'/delete', ['_token' => $token]);

        $this->assertResponseRedirects('/admin/catalog/brands');
    }

    /**
     * @return array{0: KernelBrowser, 1: CatalogAdminClient&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function adminClientWithMock(): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $container = static::getContainer();
        $mock = $this->createMock(CatalogAdminClient::class);
        $container->set(CatalogAdminClient::class, $mock);

        $this->createSchema();
        $this->loadFixtures();

        $admin = $container->get('doctrine')->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $client->loginUser($admin, 'admin');

        return [$client, $mock];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Export\Client\ExportClient;
use App\Tests\Functional\WebTestCase;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class ExportControllerTest extends WebTestCase
{
    // --- Access control ---

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/admin/export');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testRegularUserCannotAccessExportPage(): void
    {
        $client = $this->createUserClient();
        $client->request('GET', '/admin/export');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminCanAccessExportPage(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('listRecent')->willReturn([]);

        $client->request('GET', '/admin/export');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form#export-form');
        $this->assertSelectorExists('input[name="_token"]');
    }

    // --- POST submit ---

    public function testSubmitWithValidDataRedirectsWithSuccessFlash(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('listRecent')->willReturn([]);
        $mock->expects(self::once())->method('create')->willReturn(['id' => 'e1f2c3d4-0000-4000-8000-000000000000']);

        $crawler = $client->request('GET', '/admin/export');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/export', [
            'type' => 'products',
            'format' => 'csv',
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/admin/export');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-success');
    }

    public function testSubmitWhenServiceFailsShowsDangerFlash(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('listRecent')->willReturn([]);
        $mock->method('create')->willThrowException(new \RuntimeException('down'));

        $crawler = $client->request('GET', '/admin/export');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/export', [
            'type' => 'products',
            'format' => 'csv',
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/admin/export');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testSubmitWithInvalidTypeShowsDangerFlash(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('listRecent')->willReturn([]);
        $mock->expects(self::never())->method('create');

        $crawler = $client->request('GET', '/admin/export');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/export', [
            'type' => 'invalid_type',
            'format' => 'csv',
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/admin/export');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testSubmitWithInvalidCsrfTokenShowsDangerFlash(): void
    {
        [$client, $mock] = $this->adminClientWithMock();
        $mock->method('listRecent')->willReturn([]);

        $client->request('POST', '/admin/export', [
            'type' => 'products',
            'format' => 'csv',
            '_token' => 'bad-token',
        ]);

        $this->assertResponseRedirects('/admin/export');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    // --- Download ---

    public function testDownloadNonExistentJobReturns404(): void
    {
        [$client] = $this->adminClientWithMock();
        $client->request('GET', '/admin/export/download/999999');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testDownloadUnauthenticatedRedirectsToLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/admin/export/download/1');

        $this->assertResponseRedirects('/admin/login');
    }

    /**
     * @return array{0: KernelBrowser, 1: ExportClient&\PHPUnit\Framework\MockObject\MockObject}
     */
    private function adminClientWithMock(): array
    {
        $client = static::createClient();
        $client->disableReboot();

        $container = static::getContainer();
        $mock = $this->createMock(ExportClient::class);
        $container->set(ExportClient::class, $mock);

        $this->createSchema();
        $this->loadFixtures();

        $admin = $container->get('doctrine')->getRepository(User::class)->findOneBy(['email' => 'admin@example.com']);
        $client->loginUser($admin, 'admin');

        return [$client, $mock];
    }
}

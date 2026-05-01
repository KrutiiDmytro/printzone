<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\WebTestCase;

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
        // User з брандмауера 'main' не має сесії брандмауера 'admin'
        $client = $this->createUserClient();
        $client->request('GET', '/admin/export');

        $this->assertResponseRedirects('/admin/login');
    }

    public function testAdminCanAccessExportPage(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin/export');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form#export-form');
        $this->assertSelectorExists('input[name="_token"]');
    }

    // --- POST submit ---

    public function testSubmitWithValidDataRedirectsWithSuccessFlash(): void
    {
        $client = $this->createAdminClient();

        $crawler = $client->request('GET', '/admin/export');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/export', [
            'type'   => 'products',
            'format' => 'csv',
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/admin/export');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-success');
    }

    public function testSubmitWithInvalidTypeShowsDangerFlash(): void
    {
        $client = $this->createAdminClient();

        $crawler = $client->request('GET', '/admin/export');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $client->request('POST', '/admin/export', [
            'type'   => 'invalid_type',
            'format' => 'csv',
            '_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/admin/export');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testSubmitWithInvalidCsrfTokenShowsDangerFlash(): void
    {
        $client = $this->createAdminClient();

        $client->request('POST', '/admin/export', [
            'type'   => 'products',
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
        $client = $this->createAdminClient();
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
}

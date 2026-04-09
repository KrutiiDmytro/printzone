<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProductImagePresignTest extends WebTestCase
{
    public function testPresignRequiresAuthentication(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request(
            'POST',
            '/admin/product-image/presign',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"filename":"a.png","contentType":"image/png"}'
        );

        $this->assertResponseRedirects('/admin/login');
    }

    public function testPresignReturns400WhenStorageTypeIsLocal(): void
    {
        $client = $this->createAdminClient();
        $client->request('GET', '/admin');

        $client->request(
            'POST',
            '/admin/product-image/presign',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_CSRF_TOKEN' => $this->getSubmitCsrfToken($client),
            ],
            content: '{"filename":"a.png","contentType":"image/png"}'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
    }

    public function testPresignReturns403WithoutCsrfToken(): void
    {
        $client = $this->createAdminClient();

        $client->request(
            'POST',
            '/admin/product-image/presign',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"filename":"a.png","contentType":"image/png"}'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function getSubmitCsrfToken(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): string
    {
        $csrf = $client->getContainer()->get('security.csrf.token_manager');

        return $csrf->getToken('submit')->getValue();
    }
}

<?php

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\WebTestCase;

class OAuthControllerTest extends WebTestCase
{
    public function testGoogleOAuthRouteIsAccessible(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/google');

        // Google OAuth должен редиректить на accounts.google.com
        $this->assertResponseRedirects();
        $this->assertStringContainsString(
            'accounts.google.com',
            $client->getResponse()->headers->get('Location')
        );
    }

    public function testGitHubOAuthRouteIsAccessible(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/github');

        // GitHub OAuth должен редиректить на github.com
        $this->assertResponseRedirects();
        $this->assertStringContainsString(
            'github.com',
            $client->getResponse()->headers->get('Location')
        );
    }

    public function testGoogleCallbackWithoutCodeRedirectsToLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/google/callback');

        $this->assertResponseRedirects('/login');
    }

    public function testGitHubCallbackWithoutCodeRedirectsToLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/github/callback');

        $this->assertResponseRedirects('/login');
    }

    public function testGoogleOAuthRouteIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/google');

        // Маршрут доступний без авторизації (не 403)
        $this->assertNotEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testGitHubOAuthRouteIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/github');

        // Маршрут доступний без авторизації (не 403)
        $this->assertNotEquals(403, $client->getResponse()->getStatusCode());
    }
}
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

    public function testGitHubCallbackWithInvalidCodeRedirectsToLogin(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/github/callback?code=invalid_code');

        $this->assertResponseRedirects('/login');
    }

    public function testGoogleOAuthRouteIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/google');

        $this->assertNotEquals(403, $client->getResponse()->getStatusCode());
    }

    public function testGitHubOAuthRouteIsPubliclyAccessible(): void
    {
        $client = static::createClient();
        $this->createSchema();
        $this->loadFixtures();

        $client->request('GET', '/auth/github');

        $this->assertNotEquals(403, $client->getResponse()->getStatusCode());
    }
}

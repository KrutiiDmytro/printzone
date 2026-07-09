<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthTest extends WebTestCase
{
    private ?string $originalMailerDsn;

    protected function setUp(): void
    {
        $this->originalMailerDsn = $_ENV['MAILER_DSN'] ?? null;
    }

    protected function tearDown(): void
    {
        // Restore the env this test mutates so it can't leak a broken SMTP DSN
        // into later tests (e.g. NotificationEmailTest booting a real mailer).
        if (null === $this->originalMailerDsn) {
            unset($_SERVER['MAILER_DSN'], $_ENV['MAILER_DSN']);
        } else {
            $_SERVER['MAILER_DSN'] = $_ENV['MAILER_DSN'] = $this->originalMailerDsn;
        }
        parent::tearDown();
    }

    public function testLiveIsPublicAndOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/health/live');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json($client)['status']);
    }

    public function testReadyReportsSmtpOkWithNullTransport(): void
    {
        // .env.test uses MAILER_DSN=null://null → nothing to dial → ok.
        $client = static::createClient();
        $client->request('GET', '/health/ready');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', $this->json($client)['checks']['smtp']['status']);
    }

    public function testReadyReturns503WhenSmtpUnreachable(): void
    {
        // Point at a port nothing listens on → the TCP probe fails → 503.
        $_SERVER['MAILER_DSN'] = $_ENV['MAILER_DSN'] = 'smtp://127.0.0.1:1';
        $client = static::createClient();
        $client->request('GET', '/health/ready');

        self::assertResponseStatusCodeSame(503);
        self::assertSame('error', $this->json($client)['status']);
    }

    public function testUnknownRouteIs404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/no-such-route');

        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        return json_decode($client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}

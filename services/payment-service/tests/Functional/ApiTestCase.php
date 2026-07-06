<?php

namespace App\Tests\Functional;

use App\Entity\Payment;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Uid\Uuid;

abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();

        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->em);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function seedPayment(string $status = Payment::STATUS_INITIATED, ?Uuid $orderId = null): Payment
    {
        $payment = new Payment($orderId ?? Uuid::v4(), 1999);
        $payment->setStripeSessionId('cs_test_seed');
        $payment->setStatus($status);

        $this->em->persist($payment);
        $this->em->flush();

        return $payment;
    }

    /** A non-privileged service token (read-level). */
    protected function serviceToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-test', null, ['ROLE_USER']));
    }

    /** A service token allowed to create payment sessions. */
    protected function paymentAdminToken(): string
    {
        $jwt = static::getContainer()->get(JWTTokenManagerInterface::class);

        return $jwt->create(new InMemoryUser('service-payment', null, ['ROLE_USER', 'ROLE_PAYMENT_ADMIN']));
    }

    /**
     * Builds a valid Stripe-Signature header for the payload using the test
     * webhook secret, so the real signature-verification path is exercised.
     */
    protected function stripeSignature(string $payload, string $secret = 'whsec_test_dummy'): string
    {
        $timestamp = time();
        $signed = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        return sprintf('t=%d,v1=%s', $timestamp, $signed);
    }

    /**
     * @param array<string, mixed> $body
     */
    protected function send(string $method, string $uri, array $body = [], ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request($method, $uri, [], [], $server, [] === $body ? null : (string) json_encode($body));
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode($this->client->getResponse()->getContent() ?: '{}', true) ?? [];
    }
}

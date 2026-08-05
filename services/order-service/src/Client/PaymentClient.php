<?php

declare(strict_types=1);

namespace App\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Calls payment-service to create a Stripe Checkout session. Write-strict: any
 * failure throws so checkout fails loudly (order-service marks the order FAILED
 * and returns 502) rather than leaving a PENDING order with no payment session.
 *
 * Mirrors the monolith's CartClient/CatalogAdminClient — a per-request service
 * JWT (ROLE_PAYMENT_ADMIN) signed with the shared keypair authenticates the call.
 */
class PaymentClient
{
    private ?string $serviceToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire(env: 'PAYMENT_SERVICE_URL')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param list<array{name: string, price: int, quantity: int}> $lineItems
     *
     * @return array{sessionId: string, url: string}
     */
    public function createSession(string $orderId, array $lineItems, string $successUrl, string $cancelUrl): array
    {
        $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/').'/api/payments', [
            'auth_bearer' => $this->serviceToken(),
            'timeout' => 5,
            'json' => [
                'orderId' => $orderId,
                'lineItems' => $lineItems,
                'successUrl' => $successUrl,
                'cancelUrl' => $cancelUrl,
            ],
        ]);

        // false → don't throw on non-2xx; we inspect the status ourselves.
        $data = $response->toArray(false);
        if (201 !== $response->getStatusCode() || empty($data['url']) || empty($data['sessionId'])) {
            throw new \RuntimeException('payment-service create-session failed: HTTP '.$response->getStatusCode());
        }

        return ['sessionId' => (string) $data['sessionId'], 'url' => (string) $data['url']];
    }

    private function serviceToken(): string
    {
        return $this->serviceToken ??= $this->jwtManager->create(
            new InMemoryUser('service-order', null, ['ROLE_USER', 'ROLE_PAYMENT_ADMIN'])
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Order\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Order Service. The monolith no longer owns orders: checkout
 * delegates order creation + Stripe session here, and the admin/export read
 * paths fetch order rows over HTTP. Failures surface (throw) so the caller
 * (checkout, export) can react.
 */
class OrderClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        #[Autowire('%env(ORDER_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * Creates a PENDING order + Stripe Checkout session.
     *
     * @param array{userId: string, userEmail: string, items: list<array{productId: string, name: string, price: int, quantity: int}>, shippingAddress: array<string, string>, successUrl: string, cancelUrl: string} $payload
     *
     * @return array{orderId: string, url: string}
     */
    public function createCheckout(array $payload): array
    {
        /** @var array{orderId: string, url: string} $row */
        $row = $this->request('POST', '/api/checkout', $payload);

        return $row;
    }

    /**
     * @param array{status?: string, dateFrom?: string, dateTo?: string} $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $query = http_build_query(array_filter($filters, static fn ($v): bool => '' !== (string) $v));

        return $this->request('GET', '/api/orders'.($query ? '?'.$query : ''))['data'] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        $row = $this->request('GET', '/api/orders/'.$id);

        return isset($row['id']) ? $row : null;
    }

    /**
     * Admin fulfilment-status change.
     *
     * @return array<string, mixed>
     */
    public function updateStatus(string $id, string $status): array
    {
        return $this->request('PUT', '/api/orders/'.$id.'/status', ['status' => $status]);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $options = [
            'auth_bearer' => $this->serviceToken(),
            'timeout' => 5,
        ];
        if (null !== $body) {
            $options['json'] = $body;
        }

        $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/').$path, $options);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $payload = $response->toArray(false);
            throw new \RuntimeException($payload['error'] ?? sprintf('Order Service returned %d.', $status));
        }

        return 204 === $status ? [] : $response->toArray(false);
    }

    private function serviceToken(): string
    {
        return $this->jwtManager->create(new InMemoryUser('service-order', null, ['ROLE_USER', 'ROLE_ADMIN']));
    }
}

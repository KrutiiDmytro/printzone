<?php

declare(strict_types=1);

namespace App\Cart\Client;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Cart Service — the persistent cart of authenticated users.
 * Unlike the storefront CatalogClient, the cart is critical: a service failure
 * throws rather than degrading to an empty cart (which would silently lose items).
 */
class CartClient
{
    /**
     * Per-request memo of the cart per user. The navbar renders the cart count +
     * total on every page; without this each Twig call re-hits the service.
     *
     * @var array<string, list<array{productId: string, productName: string, price: int, quantity: int}>>
     */
    private array $cache = [];

    /** Per-request service JWT (ROLE_CART_ADMIN covers both reads and writes). */
    private ?string $serviceToken = null;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CART_SERVICE_URL)%')]
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @return list<array{productId: string, productName: string, price: int, quantity: int}>
     */
    public function get(string $userId): array
    {
        if (isset($this->cache[$userId])) {
            return $this->cache[$userId];
        }

        try {
            return $this->cache[$userId] = $this->request('GET', '/api/carts/'.$userId)['items'] ?? [];
        } catch (\Throwable) {
            // Reads degrade to empty so the navbar cart count keeps the storefront
            // up if cart-service is briefly unavailable. Writes below stay strict.
            return $this->cache[$userId] = [];
        }
    }

    public function addItem(string $userId, string $productId, string $productName, int $price, int $quantity): void
    {
        $this->request('POST', '/api/carts/'.$userId.'/items', [
            'productId' => $productId,
            'productName' => $productName,
            'price' => $price,
            'quantity' => $quantity,
        ]);
        unset($this->cache[$userId]);
    }

    public function updateItem(string $userId, string $productId, int $quantity): void
    {
        $this->request('PATCH', '/api/carts/'.$userId.'/items/'.$productId, ['quantity' => $quantity]);
        unset($this->cache[$userId]);
    }

    public function removeItem(string $userId, string $productId): void
    {
        $this->request('DELETE', '/api/carts/'.$userId.'/items/'.$productId);
        unset($this->cache[$userId]);
    }

    public function clear(string $userId): void
    {
        $this->request('DELETE', '/api/carts/'.$userId);
        unset($this->cache[$userId]);
    }

    /**
     * @param array<string, mixed>|null $body
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        try {
            $options = ['auth_bearer' => $this->serviceToken(), 'timeout' => 5];
            if (null !== $body) {
                $options['json'] = $body;
            }

            $response = $this->httpClient->request($method, rtrim($this->baseUrl, '/').$path, $options);
            $status = $response->getStatusCode();

            if ($status >= 400) {
                throw new \RuntimeException(sprintf('Cart Service returned HTTP %d for %s %s', $status, $method, $path));
            }

            return 204 === $status ? [] : $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('Cart Service request failed', ['method' => $method, 'path' => $path, 'error' => $e->getMessage()]);

            throw new \RuntimeException('Cart Service request failed', 0, $e);
        }
    }

    private function serviceToken(): string
    {
        return $this->serviceToken ??= $this->jwtManager->create(new InMemoryUser('service-cart', null, ['ROLE_USER', 'ROLE_CART_ADMIN']));
    }
}

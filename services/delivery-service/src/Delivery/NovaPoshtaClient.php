<?php

declare(strict_types=1);

namespace App\Delivery;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Real Nova Poshta integration (used when DELIVERY_PROVIDER=nova_poshta). Calls
 * the v2.0 JSON API to register an InternetDocument and returns its tracking
 * number. Requires NOVA_POSHTA_API_KEY; without it the service defaults to
 * FakeProvider, so this is the documented real-integration point rather than the
 * path exercised by CI.
 */
final class NovaPoshtaClient implements DeliveryProviderInterface
{
    public const NAME = 'nova_poshta';

    private const API_URL = 'https://api.novaposhta.ua/v2.0/json/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(NOVA_POSHTA_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function createShipment(string $orderId, array $address): ShipmentDraft
    {
        $response = $this->call('InternetDocument', 'save', [
            'CitySender' => $address['city'] ?? '',
            'Recipient' => trim(($address['firstName'] ?? '').' '.($address['lastName'] ?? '')),
            'RecipientAddress' => $address['address'] ?? '',
            'RecipientCity' => $address['city'] ?? '',
            'RecipientsPhone' => $address['phone'] ?? '',
            'Description' => 'Order '.$orderId,
        ]);

        $tracking = $response['data'][0]['IntDocNumber'] ?? null;
        if (!is_string($tracking) || '' === $tracking) {
            throw new \RuntimeException('Nova Poshta did not return a tracking number.');
        }

        return new ShipmentDraft($tracking, self::NAME);
    }

    public function isAvailable(): bool
    {
        if ('' === $this->apiKey) {
            return false;
        }

        try {
            $this->call('Common', 'getTimeIntervals', []);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $properties
     *
     * @return array<string, mixed>
     */
    private function call(string $model, string $method, array $properties): array
    {
        $response = $this->httpClient->request('POST', self::API_URL, [
            'json' => [
                'apiKey' => $this->apiKey,
                'modelName' => $model,
                'calledMethod' => $method,
                'methodProperties' => $properties,
            ],
            'timeout' => 5,
        ]);

        $data = $response->toArray(false);
        if (true !== ($data['success'] ?? false)) {
            $errors = implode('; ', $data['errors'] ?? ['unknown error']);
            throw new \RuntimeException('Nova Poshta API error: '.$errors);
        }

        return $data;
    }
}

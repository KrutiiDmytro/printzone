<?php

namespace App\Tests\Unit\Delivery;

use App\Delivery\NovaPoshtaClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NovaPoshtaClientTest extends TestCase
{
    public function testIsAvailableFalseWithoutApiKey(): void
    {
        $http = new MockHttpClient(function (): MockResponse {
            self::fail('No HTTP call should be made without an API key.');
        });

        self::assertFalse((new NovaPoshtaClient($http, ''))->isAvailable());
    }

    public function testIsAvailablePingsAParameterlessMethod(): void
    {
        $seen = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): MockResponse {
            $seen = json_decode($options['body'], true);

            return new MockResponse(json_encode(['success' => true, 'data' => []]));
        });

        self::assertTrue((new NovaPoshtaClient($http, 'key'))->isAvailable());
        // Must be a no-parameter method — getTimeIntervals would fail without a
        // RecipientCityRef and wrongly report the provider as down.
        self::assertSame('getCargoTypes', $seen['calledMethod']);
        self::assertSame([], $seen['methodProperties']);
    }

    public function testIsAvailableFalseWhenApiReportsFailure(): void
    {
        $http = new MockHttpClient(
            new MockResponse(json_encode(['success' => false, 'errors' => ['API key expired']]))
        );

        self::assertFalse((new NovaPoshtaClient($http, 'key'))->isAvailable());
    }

    public function testCreateShipmentReturnsTrackingNumber(): void
    {
        $http = new MockHttpClient(
            new MockResponse(json_encode(['success' => true, 'data' => [['IntDocNumber' => '20450000000001']]]))
        );

        $draft = (new NovaPoshtaClient($http, 'key'))->createShipment('order-1', ['city' => 'Kyiv']);

        self::assertSame('20450000000001', $draft->trackingNumber);
        self::assertSame('nova_poshta', $draft->provider);
    }

    public function testCreateShipmentThrowsOnApiError(): void
    {
        $http = new MockHttpClient(
            new MockResponse(json_encode(['success' => false, 'errors' => ['Recipient city not found']]))
        );

        $this->expectException(\RuntimeException::class);
        (new NovaPoshtaClient($http, 'key'))->createShipment('order-1', ['city' => 'Nowhere']);
    }
}

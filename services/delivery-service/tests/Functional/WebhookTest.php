<?php

namespace App\Tests\Functional;

use App\Entity\OutboxMessage;
use App\Entity\Shipment;
use App\Entity\TrackingEvent;

class WebhookTest extends ApiTestCase
{
    /**
     * @param array<string, mixed> $body
     */
    private function post(array $body): void
    {
        // Public firewall — Nova Poshta authenticates via the payload, no JWT.
        $this->send('POST', '/api/webhooks/novaposhta', $body);
    }

    /**
     * @return string[]
     */
    private function outboxEventNames(): array
    {
        $this->em->clear();

        return array_map(
            fn (OutboxMessage $m) => $m->getEventName(),
            $this->em->getRepository(OutboxMessage::class)->findAll()
        );
    }

    public function testCanonicalStatusAppendsTrackingAndEmitsTrackingUpdated(): void
    {
        $shipment = $this->seedShipment('FAKE_A');

        $this->post(['trackingNumber' => 'FAKE_A', 'status' => 'IN_TRANSIT', 'location' => 'Kyiv hub']);

        self::assertResponseIsSuccessful();
        self::assertTrue($this->json()['applied']);

        $fresh = $this->em->getRepository(Shipment::class)->find($shipment->getId());
        self::assertSame(Shipment::STATUS_IN_TRANSIT, $fresh->getStatus());
        self::assertCount(1, $this->em->getRepository(TrackingEvent::class)->findAll());
        self::assertContains('TrackingUpdated', $this->outboxEventNames());
    }

    public function testNovaPoshtaStatusCodeIsMapped(): void
    {
        $this->seedShipment('FAKE_B');

        // NP StatusCode 9 → DELIVERED.
        $this->post(['trackingNumber' => 'FAKE_B', 'statusCode' => '9']);

        self::assertResponseIsSuccessful();
        self::assertSame(Shipment::STATUS_DELIVERED, $this->json()['status']);
        self::assertContains('ShipmentDelivered', $this->outboxEventNames());
    }

    public function testDeliveredEmitsShipmentDelivered(): void
    {
        $this->seedShipment('FAKE_C');

        $this->post(['trackingNumber' => 'FAKE_C', 'status' => 'DELIVERED']);

        self::assertResponseIsSuccessful();
        self::assertSame(['ShipmentDelivered'], $this->outboxEventNames());
    }

    public function testReplayWithSameOccurredAtIsIdempotent(): void
    {
        $this->seedShipment('FAKE_D');
        $body = ['trackingNumber' => 'FAKE_D', 'status' => 'IN_TRANSIT', 'occurredAt' => '2026-07-07T10:00:00+00:00'];

        $this->post($body);
        $this->post($body);

        self::assertCount(1, $this->em->getRepository(TrackingEvent::class)->findAll());
        self::assertSame(['TrackingUpdated'], $this->outboxEventNames());
    }

    public function testUnknownTrackingNumberIs404(): void
    {
        $this->post(['trackingNumber' => 'NOPE', 'status' => 'IN_TRANSIT']);

        self::assertResponseStatusCodeSame(404);
    }

    public function testMissingStatusIs400(): void
    {
        $this->seedShipment('FAKE_E');

        $this->post(['trackingNumber' => 'FAKE_E']);

        self::assertResponseStatusCodeSame(400);
    }
}

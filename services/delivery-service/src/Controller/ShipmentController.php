<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Shipment;
use App\Entity\TrackingEvent;
use App\Repository\ShipmentRepository;
use App\Repository\TrackingEventRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Read API for shipments and their tracking history. Behind the JWT firewall
 * (^/api) — any valid service token can read.
 */
#[Route('/api/shipments')]
class ShipmentController
{
    private const UUID = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly ShipmentRepository $shipments,
        private readonly TrackingEventRepository $trackingEvents,
    ) {
    }

    #[Route('/{id}', name: 'shipment_get', requirements: ['id' => self::UUID], methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        $shipment = $this->shipments->find(Uuid::fromString($id));
        if (null === $shipment) {
            return new JsonResponse(['error' => 'Shipment not found'], 404);
        }

        return new JsonResponse($this->serialize($shipment));
    }

    #[Route('/order/{orderId}', name: 'shipment_by_order', requirements: ['orderId' => self::UUID], methods: ['GET'])]
    public function byOrder(string $orderId): JsonResponse
    {
        $shipment = $this->shipments->findOneByOrderId(Uuid::fromString($orderId));
        if (null === $shipment) {
            return new JsonResponse(['error' => 'Shipment not found'], 404);
        }

        return new JsonResponse($this->serialize($shipment));
    }

    #[Route('/{id}/tracking', name: 'shipment_tracking', requirements: ['id' => self::UUID], methods: ['GET'])]
    public function tracking(string $id): JsonResponse
    {
        $shipment = $this->shipments->find(Uuid::fromString($id));
        if (null === $shipment) {
            return new JsonResponse(['error' => 'Shipment not found'], 404);
        }

        $events = array_map(
            static fn (TrackingEvent $e): array => [
                'status' => $e->getStatus(),
                'location' => $e->getLocation(),
                'description' => $e->getDescription(),
                'occurredAt' => $e->getOccurredAt()->format(\DateTimeInterface::ATOM),
            ],
            $this->trackingEvents->findByShipment($shipment),
        );

        return new JsonResponse(['shipmentId' => $id, 'events' => $events]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Shipment $shipment): array
    {
        return [
            'id' => (string) $shipment->getId(),
            'orderId' => (string) $shipment->getOrderId(),
            'provider' => $shipment->getProvider(),
            'trackingNumber' => $shipment->getTrackingNumber(),
            'status' => $shipment->getStatus(),
            'address' => $shipment->getAddress(),
            'estimatedAt' => $shipment->getEstimatedAt()?->format('Y-m-d'),
            'createdAt' => $shipment->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}

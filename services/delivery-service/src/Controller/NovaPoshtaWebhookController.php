<?php

declare(strict_types=1);

namespace App\Controller;

use App\Delivery\NovaPoshtaStatusMapper;
use App\Delivery\TrackingRecorder;
use App\Entity\Shipment;
use App\Repository\ShipmentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public Nova Poshta tracking webhook (no JWT — see the `webhook` firewall). Each
 * callback appends a status to the shipment's event-sourced log; the shipment is
 * resolved by its tracking number. Accepts either a canonical `status` (used by
 * tests / the simulate command shape) or Nova Poshta's `statusCode`.
 */
#[Route('/api/webhooks/novaposhta')]
class NovaPoshtaWebhookController
{
    public function __construct(
        private readonly ShipmentRepository $shipments,
        private readonly TrackingRecorder $tracking,
        private readonly NovaPoshtaStatusMapper $statusMapper,
    ) {
    }

    #[Route('', name: 'novaposhta_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent() ?: '{}', true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON body'], 400);
        }

        $trackingNumber = (string) ($data['trackingNumber'] ?? '');
        if ('' === $trackingNumber) {
            return new JsonResponse(['error' => 'Missing trackingNumber'], 400);
        }

        $status = $this->resolveStatus($data);
        if (null === $status) {
            return new JsonResponse(['error' => 'Missing or invalid status/statusCode'], 400);
        }

        $occurredAt = $this->resolveOccurredAt($data);
        if (false === $occurredAt) {
            return new JsonResponse(['error' => 'Invalid occurredAt'], 400);
        }

        $shipment = $this->shipments->findOneByTrackingNumber($trackingNumber);
        if (null === $shipment) {
            return new JsonResponse(['error' => 'Unknown trackingNumber'], 404);
        }

        $applied = $this->tracking->record(
            $shipment,
            $status,
            isset($data['location']) ? (string) $data['location'] : null,
            isset($data['description']) ? (string) $data['description'] : null,
            $occurredAt,
        );

        return new JsonResponse([
            'shipmentId' => (string) $shipment->getId(),
            'status' => $shipment->getStatus(),
            'applied' => $applied,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveStatus(array $data): ?string
    {
        if (isset($data['status'])) {
            $status = (string) $data['status'];

            return in_array($status, Shipment::STATUSES, true) ? $status : null;
        }

        if (isset($data['statusCode'])) {
            return $this->statusMapper->map((string) $data['statusCode']);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return \DateTimeImmutable|null|false null → default to now, false → invalid
     */
    private function resolveOccurredAt(array $data): \DateTimeImmutable|null|false
    {
        if (!isset($data['occurredAt'])) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $data['occurredAt']);
        } catch (\Exception) {
            return false;
        }
    }
}

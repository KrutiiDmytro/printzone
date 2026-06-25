<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Read API consumed by the monolith: the admin order screens and the Export
 * extractor (which used to query the Order entity directly). The admin also
 * updates an order's fulfilment status via PUT /{id}/status (ROLE_ADMIN).
 */
#[Route('/api/orders')]
class OrderController
{
    private const MAX_LIMIT = 500;

    private const STATUSES = ['PENDING', 'PAID', 'FAILED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];

    public function __construct(
        private readonly OrderRepository $orders,
    ) {
    }

    #[Route('', name: 'orders_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', 100)));
        $filters = [
            'status' => (string) $request->query->get('status', ''),
            'dateFrom' => (string) $request->query->get('dateFrom', ''),
            'dateTo' => (string) $request->query->get('dateTo', ''),
        ];

        $data = array_map($this->serialize(...), $this->orders->findByFilters($filters, $limit));

        return new JsonResponse(['data' => $data, 'total' => \count($data)]);
    }

    #[Route('/{id}', name: 'orders_get', methods: ['GET'])]
    public function get(string $id): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['error' => 'Invalid order id'], 400);
        }

        $order = $this->orders->find(Uuid::fromString($id));
        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        return new JsonResponse($this->serialize($order));
    }

    #[Route('/{id}/status', name: 'orders_update_status', methods: ['PUT'])]
    public function updateStatus(string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        if (!Uuid::isValid($id)) {
            return new JsonResponse(['error' => 'Invalid order id'], 400);
        }

        $data = json_decode($request->getContent() ?: '{}', true);
        $status = is_array($data) ? (string) ($data['status'] ?? '') : '';
        if (!in_array($status, self::STATUSES, true)) {
            return new JsonResponse(['error' => 'Invalid status'], 400);
        }

        $order = $this->orders->find(Uuid::fromString($id));
        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        $order->setStatus($status);
        $em->flush();

        return new JsonResponse($this->serialize($order));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Order $order): array
    {
        $items = [];
        foreach ($order->getItems() as $item) {
            $items[] = [
                'productId' => (string) $item->getProductId(),
                'productName' => $item->getProductName(),
                'price' => $item->getPrice(),
                'quantity' => $item->getQuantity(),
            ];
        }

        return [
            'id' => (string) $order->getId(),
            'userId' => (string) $order->getUserId(),
            'userEmail' => $order->getUserEmail(),
            'status' => $order->getStatus(),
            'totalAmount' => $order->getTotalAmount(),
            'createdAt' => $order->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'items' => $items,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Order\Client\OrderClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Order admin proxied over HTTP to order-service (the source of truth). The
 * monolith no longer stores orders, so these pages read order rows and push
 * fulfilment-status changes through OrderClient instead of Doctrine + EasyAdmin.
 */
#[IsGranted('ROLE_ADMIN')]
#[Route('/admin/orders')]
final class OrderAdminController extends AbstractController
{
    private const STATUSES = ['PENDING', 'PAID', 'FAILED', 'PROCESSING', 'SHIPPED', 'DELIVERED', 'CANCELLED'];

    public function __construct(private readonly OrderClient $orders)
    {
    }

    #[Route('', name: 'admin_orders', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $filters = [
            'status' => (string) $request->query->get('status', ''),
            'dateFrom' => (string) $request->query->get('dateFrom', ''),
            'dateTo' => (string) $request->query->get('dateTo', ''),
        ];

        return $this->render('admin/order/orders.html.twig', [
            'orders' => $this->orders->list($filters),
            'filters' => $filters,
            'statuses' => self::STATUSES,
        ]);
    }

    #[Route('/{id}', name: 'admin_order_detail', methods: ['GET'])]
    public function detail(string $id): Response
    {
        $order = $this->orders->find($id);
        if (null === $order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('admin/order/order_detail.html.twig', [
            'order' => $order,
            'statuses' => self::STATUSES,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_order_status', methods: ['POST'])]
    public function updateStatus(Request $request, string $id): Response
    {
        if (!$this->isCsrfTokenValid('order_admin', $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_order_detail', ['id' => $id]);
        }

        $status = $request->request->getString('status');
        try {
            $this->orders->updateStatus($id, $status);
            $this->addFlash('success', 'Order status updated.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Update failed: '.$e->getMessage());
        }

        return $this->redirectToRoute('admin_order_detail', ['id' => $id]);
    }
}

<?php

namespace App\Controller;

use App\Delivery\DeliveryProviderInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(Connection $db, DeliveryProviderInterface $provider): JsonResponse
    {
        try {
            $start = microtime(true);
            $db->executeQuery('SELECT 1');
            $checks = ['database' => [
                'status' => 'ok',
                'latency_ms' => (int) round((microtime(true) - $start) * 1000),
            ]];
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'checks' => ['database' => ['status' => 'error', 'detail' => $e->getMessage()]],
            ], 503);
        }

        $checks['provider'] = ['status' => $provider->isAvailable() ? 'ok' : 'error'];

        return new JsonResponse(['status' => 'ok', 'checks' => $checks]);
    }
}

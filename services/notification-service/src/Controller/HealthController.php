<?php

namespace App\Controller;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HealthController
{
    public function __construct(
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
    ) {
    }

    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        // The service is stateless (no DB); its only dependency reachable in-process
        // is the mail transport. The broker is checked at the worker level, not here
        // (mirrors delivery-service, which keeps /ready cheap and self-contained).
        [$status, $detail] = $this->checkSmtp();

        if ('ok' !== $status) {
            return new JsonResponse([
                'status' => 'error',
                'checks' => ['smtp' => ['status' => 'error', 'detail' => $detail]],
            ], 503);
        }

        return new JsonResponse(['status' => 'ok', 'checks' => ['smtp' => ['status' => 'ok']]]);
    }

    /**
     * Lightweight SMTP reachability check. Non-SMTP transports (null, sendmail, the
     * SES API) have nothing to dial, so they report ok; only smtp:// DSNs open a
     * short-lived TCP probe to host:port.
     *
     * @return array{0: string, 1: string}
     */
    private function checkSmtp(): array
    {
        $scheme = (string) parse_url($this->mailerDsn, PHP_URL_SCHEME);
        if (!str_contains($scheme, 'smtp')) {
            return ['ok', ''];
        }

        $host = (string) parse_url($this->mailerDsn, PHP_URL_HOST);
        $port = (int) (parse_url($this->mailerDsn, PHP_URL_PORT) ?: 25);

        $socket = @fsockopen($host, $port, $errno, $errstr, 2.0);
        if (false === $socket) {
            return ['error', sprintf('%s:%d unreachable (%s)', $host, $port, $errstr)];
        }
        fclose($socket);

        return ['ok', ''];
    }
}

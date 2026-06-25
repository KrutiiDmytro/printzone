<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Extractor;

use App\Export\Extractor\OrderExtractor;
use App\Order\Client\OrderClient;
use PHPUnit\Framework\TestCase;

final class OrderExtractorTest extends TestCase
{
    private function clientReturning(array $rows): OrderClient
    {
        $client = $this->createMock(OrderClient::class);
        $client->method('list')->willReturn($rows);

        return $client;
    }

    public function testExtractReturnsEmptyArrayWhenNoRows(): void
    {
        $extractor = new OrderExtractor($this->clientReturning([]));
        $this->assertSame([], $extractor->extract([]));
    }

    public function testExtractTransformsRow(): void
    {
        $row = [
            'id' => '0197f0aa-1111-7000-8000-000000000005',
            'status' => 'PAID',
            'totalAmount' => 25000,
            'createdAt' => '2026-01-15T10:30:00+00:00',
            'userEmail' => 'user@example.com',
        ];

        $result = (new OrderExtractor($this->clientReturning([$row])))->extract([]);

        $this->assertCount(1, $result);
        $this->assertSame($row['id'], $result[0]['id']);
        $this->assertSame('user@example.com', $result[0]['user_email']);
        $this->assertSame('PAID', $result[0]['status']);
        $this->assertSame('250.00', $result[0]['total_amount']);
        $this->assertSame('2026-01-15 10:30:00', $result[0]['created_at']);
    }

    public function testExtractMissingUserEmailBecomesEmptyString(): void
    {
        $row = ['id' => 'x', 'status' => 'PENDING', 'totalAmount' => 0, 'createdAt' => '2026-03-01T00:00:00+00:00'];
        $result = (new OrderExtractor($this->clientReturning([$row])))->extract([]);
        $this->assertSame('', $result[0]['user_email']);
    }

    public function testExtractMissingCreatedAtBecomesEmptyString(): void
    {
        $row = ['id' => 'x', 'status' => 'PENDING', 'totalAmount' => 0, 'userEmail' => 'a@b.c'];
        $result = (new OrderExtractor($this->clientReturning([$row])))->extract([]);
        $this->assertSame('', $result[0]['created_at']);
    }

    public function testExtractForwardsFiltersToClient(): void
    {
        $client = $this->createMock(OrderClient::class);
        $client->expects($this->once())
            ->method('list')
            ->with($this->callback(function (array $filters): bool {
                return 'PAID' === $filters['status'] && '2026-01-31' === $filters['dateTo'];
            }))
            ->willReturn([]);

        (new OrderExtractor($client))->extract(['status' => 'PAID', 'dateTo' => '2026-01-31']);
    }
}

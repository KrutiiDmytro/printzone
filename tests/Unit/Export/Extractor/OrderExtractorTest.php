<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Extractor;

use App\Export\Extractor\OrderExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class OrderExtractorTest extends TestCase
{
    private function makeEm(array $rows): EntityManagerInterface
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return $em;
    }

    private function makeQbWithExpectations(array $rows): array
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return [$qb, $em];
    }

    public function testExtractReturnsEmptyArrayWhenNoRows(): void
    {
        $extractor = new OrderExtractor($this->makeEm([]));
        $this->assertSame([], $extractor->extract([]));
    }

    public function testExtractTransformsRow(): void
    {
        $createdAt = new \DateTime('2026-01-15 10:30:00');
        $row = [
            'id' => 5,
            'status' => 'PAID',
            'totalAmount' => 25000,
            'createdAt' => $createdAt,
            'user_email' => 'user@example.com',
        ];

        $result = (new OrderExtractor($this->makeEm([$row])))->extract([]);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['id']);
        $this->assertSame('user@example.com', $result[0]['user_email']);
        $this->assertSame('PAID', $result[0]['status']);
        $this->assertSame('250.00', $result[0]['total_amount']);
        $this->assertSame('2026-01-15 10:30:00', $result[0]['created_at']);
    }

    public function testExtractNullUserEmailBecomesEmptyString(): void
    {
        $row = ['id' => 6, 'status' => 'PENDING', 'totalAmount' => 0, 'createdAt' => new \DateTime(), 'user_email' => null];
        $result = (new OrderExtractor($this->makeEm([$row])))->extract([]);
        $this->assertSame('', $result[0]['user_email']);
    }

    public function testExtractCreatedAtNonDateTimeIsCast(): void
    {
        $row = ['id' => 7, 'status' => 'PENDING', 'totalAmount' => 0, 'createdAt' => '2026-03-01 00:00:00', 'user_email' => null];
        $result = (new OrderExtractor($this->makeEm([$row])))->extract([]);
        $this->assertSame('2026-03-01 00:00:00', $result[0]['created_at']);
    }

    public function testExtractAppliesStatusFilter(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->once())->method('andWhere')->with('o.status = :status')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('status', 'PAID')->willReturnSelf();

        (new OrderExtractor($em))->extract(['status' => 'PAID']);
    }

    public function testExtractAppliesDateToWithEndOfDay(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->once())->method('andWhere')->with('o.createdAt <= :dateTo')->willReturnSelf();
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('dateTo', $this->callback(function (\DateTime $dt): bool {
                return '2026-01-31 23:59:59' === $dt->format('Y-m-d H:i:s');
            }))
            ->willReturnSelf();

        (new OrderExtractor($em))->extract(['dateTo' => '2026-01-31']);
    }

    public function testExtractDoesNotAddWhereWhenFiltersEmpty(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        (new OrderExtractor($em))->extract([]);
    }
}

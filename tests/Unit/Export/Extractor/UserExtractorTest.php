<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Extractor;

use App\Export\Extractor\UserExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class UserExtractorTest extends TestCase
{
    private function makeEm(array $rows): EntityManagerInterface
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
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
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return [$qb, $em];
    }

    public function testExtractReturnsEmptyArrayWhenNoRows(): void
    {
        $extractor = new UserExtractor($this->makeEm([]));
        $this->assertSame([], $extractor->extract([]));
    }

    public function testExtractTransformsRow(): void
    {
        $row = [
            'id' => 1,
            'email' => 'admin@example.com',
            'fullName' => 'Admin User',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
        ];

        $result = (new UserExtractor($this->makeEm([$row])))->extract([]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('admin@example.com', $result[0]['email']);
        $this->assertSame('Admin User', $result[0]['full_name']);
        $this->assertSame('ROLE_USER, ROLE_ADMIN', $result[0]['roles']);
    }

    public function testExtractNullFullNameBecomesEmptyString(): void
    {
        $row = ['id' => 2, 'email' => 'x@x.com', 'fullName' => null, 'roles' => ['ROLE_USER']];
        $result = (new UserExtractor($this->makeEm([$row])))->extract([]);
        $this->assertSame('', $result[0]['full_name']);
    }

    public function testExtractSingleRoleIsNotImplodedWithTrailingComma(): void
    {
        $row = ['id' => 3, 'email' => 'x@x.com', 'fullName' => 'X', 'roles' => ['ROLE_USER']];
        $result = (new UserExtractor($this->makeEm([$row])))->extract([]);
        $this->assertSame('ROLE_USER', $result[0]['roles']);
    }

    public function testExtractAppliesEmailFilter(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->once())->method('andWhere')->with('u.email LIKE :email')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('email', '%john%')->willReturnSelf();

        (new UserExtractor($em))->extract(['email' => 'john']);
    }

    public function testExtractAppliesRoleFilter(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->once())->method('andWhere')->with('u.roles LIKE :role')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('role', '%ROLE_ADMIN%')->willReturnSelf();

        (new UserExtractor($em))->extract(['role' => 'ROLE_ADMIN']);
    }

    public function testExtractDoesNotAddWhereWhenFiltersEmpty(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        (new UserExtractor($em))->extract([]);
    }
}

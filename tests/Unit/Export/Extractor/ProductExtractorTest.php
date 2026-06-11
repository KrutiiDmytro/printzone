<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Extractor;

use App\Export\Extractor\ProductExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class ProductExtractorTest extends TestCase
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
        $extractor = new ProductExtractor($this->makeEm([]));
        $this->assertSame([], $extractor->extract([]));
    }

    public function testExtractTransformsRow(): void
    {
        $row = [
            'id' => 1,
            'name' => 'Laptop',
            'description' => 'A laptop',
            'price' => 99900,
            'stock' => 10,
            'isFeatured' => true,
            'image' => 'products/laptop.jpg',
            'category' => 'Electronics',
        ];

        $result = (new ProductExtractor($this->makeEm([$row])))->extract([]);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('Laptop', $result[0]['name']);
        $this->assertSame('999.00', $result[0]['price']);
        $this->assertSame(10, $result[0]['stock']);
        $this->assertSame('yes', $result[0]['is_featured']);
        $this->assertSame('Electronics', $result[0]['category']);
        $this->assertSame('products/laptop.jpg', $result[0]['image']);
    }

    public function testExtractIsFeaturedFalseOutputsNo(): void
    {
        $row = ['id' => 2, 'name' => 'X', 'description' => 'x', 'price' => 0, 'stock' => 0, 'isFeatured' => false, 'image' => null, 'category' => null];
        $result = (new ProductExtractor($this->makeEm([$row])))->extract([]);
        $this->assertSame('no', $result[0]['is_featured']);
    }

    public function testExtractHandlesNullOptionalFields(): void
    {
        $row = ['id' => 3, 'name' => 'Y', 'description' => null, 'price' => 500, 'stock' => 5, 'isFeatured' => false, 'image' => null, 'category' => null];
        $result = (new ProductExtractor($this->makeEm([$row])))->extract([]);
        $this->assertSame('', $result[0]['description']);
        $this->assertSame('', $result[0]['image']);
        $this->assertSame('', $result[0]['category']);
    }

    public function testExtractAppliesCategoryFilter(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->once())->method('andWhere')->with('c.id = :category')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('category', 3)->willReturnSelf();

        (new ProductExtractor($em))->extract(['category' => '3']);
    }

    public function testExtractAppliesIsFeaturedFilter(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->once())->method('andWhere')->with('p.isFeatured = :featured')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('featured', true)->willReturnSelf();

        (new ProductExtractor($em))->extract(['isFeatured' => '1']);
    }

    public function testExtractDoesNotAddWhereWhenFiltersEmpty(): void
    {
        [$qb, $em] = $this->makeQbWithExpectations([]);
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        (new ProductExtractor($em))->extract([]);
    }
}

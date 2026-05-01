<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Formatter;

use App\Export\Formatter\CsvFormatter;
use PHPUnit\Framework\TestCase;

final class CsvFormatterTest extends TestCase
{
    private CsvFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new CsvFormatter();
    }

    public function testEmptyRowsReturnsEmptyString(): void
    {
        $this->assertSame('', $this->formatter->format([]));
    }

    public function testFormatWritesHeaderAndRows(): void
    {
        $rows = [
            ['id' => 1, 'name' => 'Product A', 'price' => '10.00'],
            ['id' => 2, 'name' => 'Product B', 'price' => '20.00'],
        ];

        $output = $this->formatter->format($rows);
        $lines = array_filter(explode("\n", trim($output)));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('id', $lines[0]);
        $this->assertStringContainsString('name', $lines[0]);
        $this->assertStringContainsString('Product A', $lines[1]);
        $this->assertStringContainsString('Product B', $lines[2]);
    }

    public function testExtensionAndContentType(): void
    {
        $this->assertSame('csv', $this->formatter->extension());
        $this->assertSame('text/csv', $this->formatter->contentType());
    }

    public function testLargeDataset(): void
    {
        $rows = [];
        for ($i = 1; $i <= 1000; $i++) {
            $rows[] = ['id' => $i, 'name' => "Product $i", 'price' => number_format($i * 9.99, 2)];
        }

        $output = $this->formatter->format($rows);
        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(1001, $lines); // 1 header + 1000 data rows
    }

    public function testSpecialCharsAreProperlyQuoted(): void
    {
        $rows = [
            ['name' => 'Product, with comma', 'desc' => 'Has "quotes" inside'],
        ];

        $output = $this->formatter->format($rows);

        $this->assertStringContainsString('Product, with comma', $output);
        $this->assertStringContainsString('quotes', $output);
        // Must be valid parseable CSV
        $lines = str_getcsv(trim($output), "\n");
        $this->assertCount(2, $lines);
    }
}

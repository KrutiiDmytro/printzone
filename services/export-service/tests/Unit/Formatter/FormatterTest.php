<?php

namespace App\Tests\Unit\Formatter;

use App\Formatter\CsvFormatter;
use App\Formatter\JsonFormatter;
use App\Formatter\XmlFormatter;
use PHPUnit\Framework\TestCase;

class FormatterTest extends TestCase
{
    private const ROWS = [
        ['id' => '1', 'name' => 'Mug', 'price' => '9.99'],
        ['id' => '2', 'name' => 'Tée', 'price' => '19.00'],
    ];

    public function testCsvHasHeaderAndRows(): void
    {
        $out = (new CsvFormatter())->format(self::ROWS);

        self::assertStringStartsWith('id,name,price', $out);
        self::assertStringContainsString('1,Mug,9.99', $out);
        self::assertSame('csv', (new CsvFormatter())->extension());
    }

    public function testCsvEmptyIsEmptyString(): void
    {
        self::assertSame('', (new CsvFormatter())->format([]));
    }

    public function testJsonRoundTrips(): void
    {
        $out = (new JsonFormatter())->format(self::ROWS);

        self::assertSame(self::ROWS, json_decode($out, true));
        self::assertStringContainsString('Tée', $out);
    }

    public function testXmlWrapsItems(): void
    {
        $out = (new XmlFormatter())->format(self::ROWS);

        self::assertStringContainsString('<items>', $out);
        self::assertStringContainsString('<name>Mug</name>', $out);
        self::assertSame('application/xml', (new XmlFormatter())->contentType());
    }
}

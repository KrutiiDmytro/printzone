<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Formatter;

use App\Export\Formatter\XmlFormatter;
use PHPUnit\Framework\TestCase;

final class XmlFormatterTest extends TestCase
{
    private XmlFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new XmlFormatter();
    }

    public function testFormatReturnsValidXml(): void
    {
        $rows = [['id' => 1, 'name' => 'Test Item']];
        $output = $this->formatter->format($rows);

        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
        $this->assertSame('export', $xml->getName());
        $this->assertSame('1', (string) $xml->items->item->id);
        $this->assertSame('Test Item', (string) $xml->items->item->name);
    }

    public function testSpecialCharsAreEscaped(): void
    {
        $rows = [['name' => '<script>alert("xss")</script>']];
        $output = $this->formatter->format($rows);

        $this->assertStringNotContainsString('<script>', $output);
        $xml = simplexml_load_string($output);
        $this->assertNotFalse($xml);
    }

    public function testExtensionAndContentType(): void
    {
        $this->assertSame('xml', $this->formatter->extension());
        $this->assertSame('application/xml', $this->formatter->contentType());
    }
}

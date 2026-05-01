<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Formatter;

use App\Export\Formatter\JsonFormatter;
use PHPUnit\Framework\TestCase;

final class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
    }

    public function testFormatReturnsValidJson(): void
    {
        $rows = [['id' => 1, 'name' => 'Test']];
        $output = $this->formatter->format($rows);
        $decoded = json_decode($output, true);

        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded[0]['id']);
        $this->assertSame('Test', $decoded[0]['name']);
    }

    public function testEmptyArrayReturnsEmptyJsonArray(): void
    {
        $this->assertSame('[]', $this->formatter->format([]));
    }

    public function testExtensionAndContentType(): void
    {
        $this->assertSame('json', $this->formatter->extension());
        $this->assertSame('application/json', $this->formatter->contentType());
    }
}

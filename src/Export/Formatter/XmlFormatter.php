<?php

declare(strict_types=1);

namespace App\Export\Formatter;

final class XmlFormatter implements ExportFormatterInterface
{
    public function format(array $rows): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><export/>');
        $items = $xml->addChild('items');

        foreach ($rows as $row) {
            $item = $items->addChild('item');
            foreach ($row as $key => $value) {
                $item->addChild((string) $key, htmlspecialchars((string) ($value ?? ''), ENT_XML1, 'UTF-8'));
            }
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML((string) $xml->asXML());

        return (string) $dom->saveXML();
    }

    public function contentType(): string
    {
        return 'application/xml';
    }

    public function extension(): string
    {
        return 'xml';
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use Contenir\Extension\Api\Section\Element;
use DOMDocument;
use DOMElement;
use DOMText;

use function trim;

/**
 * A `<seo>` block written as XML, so a section's saved strategy can be kept and read back as the element
 * admin.xml would have given.
 */
final class StrategyXml
{
    public static function write(Element $element): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        self::append($document, $element);

        return trim((string) $document->saveXML($document->documentElement));
    }

    private static function append(DOMDocument|DOMElement $parent, Element $element): void
    {
        $node = new DOMElement($element->name);
        $parent->appendChild($node);
        foreach ($element->attributes as $name => $value) {
            $node->setAttribute($name, $value);
        }

        if ('' !== $element->text) {
            $node->appendChild(new DOMText($element->text));
        }

        foreach ($element->children as $child) {
            self::append($node, $child);
        }
    }
}

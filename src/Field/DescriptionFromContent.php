<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Seo\Section\DerivedContent;
use Contenir\Seo\Sections\SeoBlock;

use function in_array;
use function is_string;
use function trim;

/**
 * The description a record's Section field gives where its own columns give none, as far as its section lets the
 * description take content.
 */
final readonly class DescriptionFromContent
{
    /**
     * @param array<string, mixed> $record  The record's values by column name
     * @param ?SeoBlock            $section The record's section's block, when it is known
     */
    public static function of(array $record, ?SeoBlock $section): ?string
    {
        if (self::holds($record) || (null !== $section && ! in_array('description', $section->content, strict: true))) {
            return null;
        }

        return (new DerivedContent($record))->description();
    }

    /**
     * Whether the record's own columns hold a description, as a section reads them by convention.
     *
     * @param array<string, mixed> $record
     */
    private static function holds(array $record): bool
    {
        foreach (SeoBlock::CONVENTIONS['description'] as $column) {
            /** @var mixed $text */
            $text = $record[$column] ?? null;
            if (is_string($text) && '' !== trim($text)) {
                return true;
            }
        }

        return false;
    }
}

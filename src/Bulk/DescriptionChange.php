<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Seo\Sections\SeoBlock;

use function is_scalar;
use function trim;

/**
 * The description a record with none would get, written from its summary text into the first of the
 * description role's columns the section has.
 */
final readonly class DescriptionChange
{
    /**
     * The column and the text to write, or null when the record has a description already, there is nowhere to
     * put one, or nothing to write it from.
     *
     * @return array{string, ?string, string}|null The column, what it holds now and what to write
     */
    public static function of(SectionBlock $block, SeoBlock $seo, RecordRow $row): ?array
    {
        $target = self::target($block, $seo);
        if (null === $target || null !== (new MetaValues($block, $row->values, $seo))->text('description')) {
            return null;
        }

        foreach (SeoSectionBlock::SUMMARY as $column) {
            $written = Summary::of(self::text($row->value($column)) ?? '');
            if (null !== $written) {
                return [$target, self::text($row->value($target)), $written];
            }
        }

        return null;
    }

    /**
     * The column a written description goes in, or null when the section has none to hold one.
     */
    public static function target(SectionBlock $block, SeoBlock $seo): ?string
    {
        foreach ($seo->roles['description'] ?? [] as $column) {
            if ($block->hasField($column)) {
                return $column;
            }
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }
}

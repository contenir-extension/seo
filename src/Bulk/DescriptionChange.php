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
     * What to write and where, or null when the record has a description already, there is nowhere to put one,
     * or nothing to write it from. A section with an advanced column keeps it there, as the record's own
     * description, as the record's SEO tab does; else it goes in the first description column.
     *
     * @return array{string, ?string, string, ?string}|null The column, what it holds now, what to write and the key it is under
     */
    public static function of(SectionBlock $block, SeoBlock $seo, RecordRow $row): ?array
    {
        $target = self::target($block, $seo);
        if (null === $target[0] || null !== (new MetaValues($block, $row->values, $seo))->text('description')) {
            return null;
        }

        foreach (SeoSectionBlock::SUMMARY as $column) {
            $written = Summary::of(self::text($row->value($column)) ?? '');
            if (null !== $written) {
                return [$target[0], ColumnValue::read($row->value($target[0]), $target[1]), $written, $target[1]];
            }
        }

        return null;
    }

    /**
     * The column a written description goes in, and the key it is under in that column: the advanced column's
     * `description` when the block has one, else the first of the description role's columns the section has.
     *
     * @return array{?string, ?string}
     */
    public static function target(SectionBlock $block, SeoBlock $seo): array
    {
        if (null !== $seo->advanced && $block->hasField($seo->advanced)) {
            return [$seo->advanced, 'description'];
        }

        foreach ($seo->roles['description'] ?? [] as $column) {
            if ($block->hasField($column)) {
                return [$column, null];
            }
        }

        return [null, null];
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
    }
}

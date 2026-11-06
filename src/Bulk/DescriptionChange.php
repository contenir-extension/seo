<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Seo\Sections\SeoBlock;

use function mb_strlen;

/**
 * The description a record with none would get, written from its summary text, or else from the first text of its
 * Section field, into the first of the description role's columns the section has.
 */
final readonly class DescriptionChange
{
    /**
     * What to write and where, or null when the record has a description already, there is nowhere to put one,
     * or nothing to write it from. A section with an advanced column keeps it there, as the record's own
     * description, as the record's SEO tab does; else it goes in the first description column.
     *
     * @return array{string, null, string, ?string}|null The column, what it holds now (nothing, or the record would have a description), what to write and the key it is under
     */
    public static function of(SectionBlock $block, SeoBlock $seo, RecordRow $row): ?array
    {
        $target = self::target($block, $seo);
        $values = new MetaValues($block, $row->values, $seo);
        if (null === $target[0] || null !== $values->text('description')) {
            return null;
        }

        foreach (SeoSectionBlock::SUMMARY as $column) {
            $written = Summary::of((string) $row->value($column));
            if (null !== $written && mb_strlen($written) >= Summary::MINIMUM) {
                return [$target[0], null, $written, $target[1]];
            }
        }

        $content = $values->derivedDescription();

        return (
            null !== $content && mb_strlen($content) >= Summary::MINIMUM
                ? [$target[0], null, $content, $target[1]]
                : null
        );
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
}

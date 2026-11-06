<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Seo\Sections\ContentBlocks;
use Contenir\Seo\Sections\SeoBlock;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function sprintf;

/**
 * What a fix would change on the records ticked, worked out from their saved values and changing nothing:
 * a record the fix has nothing to do for, because it is already so or has nothing to write from, is left out.
 */
final readonly class ChangePlan
{
    /**
     * @param array<string, string> $titles Each section's title, by section
     */
    public function __construct(
        private SectionsInterface $sections,
        private array $titles = [],
    ) {}

    /**
     * @param list<string|int> $ids
     *
     * @return list<string|int>
     */
    private static function distinct(array $ids): array
    {
        return array_values(array_unique($ids));
    }

    /**
     * @param list<RecordReference> $records
     *
     * @return list<Change>
     *
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    public function build(FixKind $kind, array $records): array
    {
        $found = [];
        foreach ($this->bySection($records) as $section => $ids) {
            $block = $this->sections->block($section);
            if (null === $block) {
                continue;
            }

            $seo     = SeoDeclaration::read($block->element);
            $columns = SeoSectionBlock::known($block, [
                ...array_merge([], ...array_values($seo->roles)),
                ...(null === $seo->advanced ? [] : [$seo->advanced]),
                ...SeoSectionBlock::SUMMARY,
                ContentBlocks::COLUMN,
            ]);
            $rows    = $this->sections->rows($section, $columns, $ids, count($ids));
            $found[] = array_map(fn(RecordRow $row): ?Change => $this->change($kind, $block, $seo, $row), $rows);
        }

        return array_values(array_filter(array_merge([], ...$found)));
    }

    /**
     * @param list<RecordReference> $records
     *
     * @return array<string, list<string|int>> Ids, by section
     */
    private function bySection(array $records): array
    {
        $by = [];
        foreach ($records as $record) {
            $by[$record->section][] = $record->id;
        }

        return array_map(self::distinct(...), $by);
    }

    private function change(FixKind $kind, SectionBlock $block, SeoBlock $seo, RecordRow $row): ?Change
    {
        $made = FixKind::WriteDescriptions === $kind
            ? DescriptionChange::of($block, $seo, $row)
            : SwitchChange::of($kind, $block, $seo, $row);
        if (null === $made) {
            return null;
        }

        return new Change(
            $row->record,
            (new MetaValues($block, $row->values, $seo))->text('title') ?? sprintf('#%s', $row->record->id),
            $this->titles[$block->section] ?? $block->section,
            $kind,
            $made[0],
            $made[1],
            $made[2],
            $made[3],
        );
    }
}

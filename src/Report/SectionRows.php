<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Seo\Section\SeoSectionBlock;

use function array_filter;
use function array_merge;
use function array_values;
use function count;

/**
 * A section's records with the columns its block reads, a page at a time, up to
 * SearchReport::MAX_ROWS of them. Only a published record has a page for search to
 * find, so the others are left to the save check and the panel. A section whose
 * columns the site won't lend reads as having no records, so the other sections are
 * still reported; the sections check says which and why.
 */
final readonly class SectionRows
{
    public function __construct(
        private SectionsInterface $sections,
    ) {}

    /**
     * @return list<RecordRow>
     */
    public function of(SectionBlock $block): array
    {
        $columns = SeoSectionBlock::reported($block);
        $pages   = [];
        try {
            for ($offset = 0; $offset < SearchReport::MAX_ROWS; $offset += SearchReport::PAGE) {
                $page    = $this->sections->rows($block->section, $columns, limit: SearchReport::PAGE, offset: $offset);
                $pages[] = $page;
                if (SearchReport::PAGE !== count($page)) {
                    break;
                }
            }
        } catch (RowsRefusedException) {
            return [];
        }

        return array_values(array_filter(
            array_merge([], ...$pages),
            static fn(RecordRow $row): bool => RecordStatus::Published === $row->status,
        ));
    }
}

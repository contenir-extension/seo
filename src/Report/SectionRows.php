<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;

use function array_filter;
use function array_merge;
use function array_values;
use function count;

/**
 * A section's records with the columns its block reads, a page at a time, up to
 * SearchReport::MAX_ROWS of them. Only a published record has a page for search to
 * find, so the others are left to the save check and the panel.
 */
final readonly class SectionRows
{
    public function __construct(
        private SectionsInterface $sections,
    ) {}

    /**
     * @return list<RecordRow>
     *
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public function of(SectionBlock $block): array
    {
        $columns = SeoSectionBlock::known($block, SeoDeclaration::read($block->element)->columns());
        $pages   = [];
        $offset  = 0;
        do {
            $page    = $this->sections->rows($block->section, $columns, limit: SearchReport::PAGE, offset: $offset);
            $pages[] = $page;
            $offset  += SearchReport::PAGE;
        } while (SearchReport::PAGE === count($page) && $offset < SearchReport::MAX_ROWS);

        return array_values(array_filter(
            array_merge([], ...$pages),
            static fn(RecordRow $row): bool => RecordStatus::Published === $row->status,
        ));
    }
}

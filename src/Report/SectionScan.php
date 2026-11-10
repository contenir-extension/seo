<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Inspect\InspectionStore;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaIssues;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Listener\TitleTaken;
use Contenir\Extension\Seo\Section\Hidden;
use Contenir\Extension\Seo\Section\Noindex;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\Title\TitleTemplate;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function sprintf;

use const ARRAY_FILTER_USE_KEY;

/**
 * Finds what each record of a section needs: going by what the site last
 * rendered for it, while its columns haven't changed since, or else by its columns.
 * A record hidden from search is only noted as hidden, and takes no part in the
 * titles the others are compared with: what search engines never see needs nothing.
 *
 * @mago-expect lint:cyclomatic-complexity Each record is judged as hidden, by the site's answer or by its columns, then against the section's titles.
 */
final readonly class SectionScan
{
    /**
     * @param array<string, Inspection> $inspections What the site last rendered, by InspectionStore::key()
     * @param string                    $state       The rendering state those answers must have been given under
     * @param string                    $robots      The robots value the site gives every page
     */
    public function __construct(
        private SectionsInterface $sections,
        private TitleTemplate $template,
        private array $inspections = [],
        private string $state = '',
        private string $robots = '',
    ) {}

    /**
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public function of(SectionBlock $block, string $title): SectionIssues
    {
        $seo     = SeoDeclaration::read($block->element);
        $rows    = (new SectionRows($this->sections))->of($block);
        $issues  = new MetaIssues($seo, $this->template);
        $switch  = $block->hasField(Hidden::column($seo)) ? Hidden::column($seo) : '';
        $reasons = array_map(fn(RecordRow $row): ?string => $this->hidden($seo, $row), $rows);
        $shared  = new SharedTitles(array_values(array_map(
            static fn(RecordRow $row): ?string => (new MetaValues($block, $row->values, $seo))->text('title'),
            array_filter(
                $rows,
                static fn(int $index): bool => null === ($reasons[$index] ?? null),
                ARRAY_FILTER_USE_KEY,
            ),
        )));
        $records   = [];
        $inspected = 0;
        foreach ($rows as $index => $row) {
            $values    = new MetaValues($block, $row->values, $seo);
            $text      = $values->text('title');
            $site      = $this->current($row);
            $reason    = $reasons[$index] ?? null;
            $inspected += null === $site ? 0 : 1;
            $found     = match (true) {
                null !== $reason => [new MetaIssue('discoverable', $switch, $reason)],
                null !== $site => InspectionIssues::of($site, $seo),
                default => $issues->of($values),
            };
            if (null === $reason && $shared->has($text)) {
                $found[] = new MetaIssue('title', $values->field('title'), TitleTaken::MESSAGE);
            }

            if ([] !== $found) {
                $records[] = new RecordIssues($row->record, $text ?? sprintf('#%s', $row->record->id), $found);
            }
        }

        $applies = ['title', 'description'];
        if ([] !== ($seo->roles['image'] ?? [])) {
            $applies[] = 'image';
        }

        if ('' !== $switch || [] !== array_filter($reasons)) {
            $applies[] = 'discoverable';
        }

        return new SectionIssues($block->section, $title, count($rows), $records, $inspected, $applies);
    }

    /**
     * What the site last rendered for the record, while that still stands.
     */
    private function current(RecordRow $row): ?Inspection
    {
        $site = $this->inspections[InspectionStore::key($row->record)] ?? null;

        return null !== $site && $site->isCurrent($row, $this->state) ? $site : null;
    }

    private function hidden(SeoBlock $seo, RecordRow $row): ?string
    {
        $site = $this->current($row);

        return Hidden::reason(
            $seo,
            $row->values,
            null !== $site && Noindex::onPage($site->robots, $site->robotsHeader, $this->robots),
        );
    }
}

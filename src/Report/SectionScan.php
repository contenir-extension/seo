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
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Seo\Title\TitleTemplate;

use function array_map;
use function count;
use function in_array;
use function sprintf;

/**
 * Finds what each record of a section needs: going by what the site last
 * rendered for it, while its columns haven't changed since, or else by its columns.
 */
final readonly class SectionScan
{
    /** What a discoverable column holds when its switch is off. */
    private const array OFF = ['0', 0, false, ''];

    /**
     * @param array<string, Inspection> $inspections What the site last rendered, by InspectionStore::key()
     */
    public function __construct(
        private SectionsInterface $sections,
        private TitleTemplate $template,
        private array $inspections = [],
    ) {}

    private static function hidden(?string $column, RecordRow $row): bool
    {
        return null !== $column
            && in_array($row->value($column), self::OFF, strict: true);
    }

    /**
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public function of(SectionBlock $block, string $title): SectionIssues
    {
        $seo    = SeoDeclaration::read($block->element);
        $rows   = (new SectionRows($this->sections))->of($block);
        $issues = new MetaIssues($seo, $this->template);
        $switch = $seo->roles['discoverable'][0] ?? null;
        $shared = new SharedTitles(array_map(
            static fn(RecordRow $row): ?string => (new MetaValues($block, $row->values, $seo))->text('title'),
            $rows,
        ));
        $records   = [];
        $inspected = 0;
        foreach ($rows as $row) {
            $values    = new MetaValues($block, $row->values, $seo);
            $text      = $values->text('title');
            $site      = $this->inspections[InspectionStore::key($row->record)] ?? null;
            $asked     = null !== $site && $site->isCurrent($row);
            $found     = $asked ? InspectionIssues::of($site) : $issues->of($values);
            $inspected += $asked ? 1 : 0;
            if ($shared->has($text)) {
                $found[] = new MetaIssue('title', $values->field('title'), TitleTaken::MESSAGE);
            }

            if (self::hidden($switch, $row)) {
                $found[] = new MetaIssue('discoverable', (string) $switch, SearchReport::HIDDEN);
            }

            if ([] !== $found) {
                $records[] = new RecordIssues($row->record, $text ?? sprintf('#%s', $row->record->id), $found);
            }
        }

        return new SectionIssues($block->section, $title, count($rows), $records, $inspected);
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaIssues;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Listener\TitleTaken;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Seo\Title\TitleTemplate;

use function array_map;
use function array_merge;
use function count;
use function in_array;
use function sprintf;

/**
 * Reads one section a page at a time, up to SearchReport::MAX_ROWS records,
 * and finds what each of its records needs.
 */
final readonly class SectionScan
{
    /** What a discoverable column holds when its switch is off. */
    private const array OFF = ['0', 0, false, ''];

    public function __construct(
        private SectionsInterface $sections,
        private TitleTemplate $template,
    ) {}

    private static function hidden(?string $column, RecordRow $row): bool
    {
        return (
            null !== $column
                && RecordStatus::Published === $row->status
                && in_array($row->value($column), self::OFF, strict: true)
        );
    }

    /**
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public function of(SectionBlock $block, string $title): SectionIssues
    {
        $seo    = SeoDeclaration::read($block->element);
        $rows   = $this->rows($block->section, SeoSectionBlock::known($block, $seo->columns()));
        $issues = new MetaIssues($seo, $this->template);
        $named  = $seo->roles['title'] ?? [];
        $switch = $seo->roles['discoverable'][0] ?? null;
        $shared = new SharedTitles(array_map(
            static fn(RecordRow $row): ?string => (new MetaValues($block, $row->values))->text($named),
            $rows,
        ));
        $records = [];
        foreach ($rows as $row) {
            $values = new MetaValues($block, $row->values);
            $text   = $values->text($named);
            $found  = $issues->of($values);
            if ($shared->has($text)) {
                $found[] = new MetaIssue('title', $values->field($named), TitleTaken::MESSAGE);
            }

            if (self::hidden($switch, $row)) {
                $found[] = new MetaIssue('discoverable', (string) $switch, SearchReport::HIDDEN);
            }

            if ([] !== $found) {
                $records[] = new RecordIssues($row->record, $text ?? sprintf('#%s', $row->record->id), $found);
            }
        }

        return new SectionIssues($block->section, $title, count($rows), $records);
    }

    /**
     * @param list<string> $columns
     *
     * @return list<RecordRow>
     *
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    private function rows(string $section, array $columns): array
    {
        $pages  = [];
        $offset = 0;
        do {
            $page    = $this->sections->rows($section, $columns, limit: SearchReport::PAGE, offset: $offset);
            $pages[] = $page;
            $offset  += SearchReport::PAGE;
        } while (SearchReport::PAGE === count($page) && $offset < SearchReport::MAX_ROWS);

        return array_merge([], ...$pages);
    }
}

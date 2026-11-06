<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;

use function array_values;
use function in_array;

/**
 * How one section's records read in search: its score, and how many records have each kind of issue. The
 * score is the share of the checks that apply, across all its published records, that pass, each counting
 * for its tier.
 */
final readonly class SectionHealth
{
    /**
     * @param array<string, int> $counts  Records with each kind of issue, by the kind's value
     * @param int                $total   What the checks that apply are worth in all
     * @param int                $failing What the failing ones are worth
     *
     * @mago-expect lint:excessive-parameter-list One parameter per thing a section's health says.
     */
    public function __construct(
        public string $section,
        public string $title,
        public int $checked,
        public Score $score,
        public array $counts,
        public int $total,
        public int $failing,
        public bool $highOpen,
    ) {}

    public static function of(SectionIssues $section): self
    {
        $counts = [];
        foreach ($section->records as $record) {
            foreach (self::kindsOf($record) as $kind) {
                $counts[$kind->value] = ($counts[$kind->value] ?? 0) + 1;
            }
        }

        $total    = 0;
        $failing  = 0;
        $highOpen = false;
        foreach (IssueKind::cases() as $kind) {
            $checked = self::checkedFor($section, $kind);
            if (0 === $checked) {
                continue;
            }

            $total    += $kind->tier()->weight() * $checked;
            $broken   = $counts[$kind->value] ?? 0;
            $failing  += $kind->tier()->weight() * $broken;
            $highOpen = $highOpen || (Tier::High === $kind->tier() && 0 < $broken);
        }

        return new self(
            $section->section,
            $section->title,
            $section->checked,
            Score::of($total, $failing, $highOpen ? Tier::High : null),
            $counts,
            $total,
            $failing,
            $highOpen,
        );
    }

    /**
     * How many of the section's records a check of this kind could be made of: none when the section
     * does not declare what it is about, and for a small share image only the records the site rendered.
     */
    private static function checkedFor(SectionIssues $section, IssueKind $kind): int
    {
        return match (true) {
            IssueKind::SmallImage === $kind => in_array('image', $section->applies, strict: true)
                ? $section->inspected
                : 0,
            default => in_array($kind->role(), $section->applies, strict: true) ? $section->checked : 0,
        };
    }

    /**
     * The kinds of issue a record has, each once.
     *
     * @return list<IssueKind>
     */
    private static function kindsOf(RecordIssues $record): array
    {
        $kinds = [];
        foreach ($record->issues as $issue) {
            $kinds[IssueKind::of($issue)->value] = IssueKind::of($issue);
        }

        return array_values($kinds);
    }

    /**
     * How many records have this kind of issue.
     */
    public function count(IssueKind $kind): int
    {
        return $this->counts[$kind->value] ?? 0;
    }
}

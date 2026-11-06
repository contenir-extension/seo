<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

use Contenir\Extension\Seo\Report\SectionIssues;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_sum;
use function in_array;
use function usort;

/**
 * How the whole site reads in search: one score from every section's checks and the site-wide ones (the
 * sitemap, the search defaults), a score for each section, and the issues to fix, the most important
 * first. A high-impact issue open anywhere keeps the site from Good.
 *
 * @mago-expect lint:cyclomatic-complexity One walk over the kinds for the score and another for what to fix.
 */
final readonly class SiteHealth
{
    /**
     * @param list<SectionHealth> $sections
     * @param list<IssueKind>     $siteIssues The site-wide kinds that are failing
     */
    public function __construct(
        public Score $score,
        public array $sections,
        public array $siteIssues,
    ) {}

    /**
     * @param list<SectionIssues> $sections
     * @param array<string, bool> $siteChecks Each site-wide kind that applies, by its value: true while it fails
     */
    public static function of(array $sections, array $siteChecks): self
    {
        $health   = array_map(SectionHealth::of(...), $sections);
        $total    = array_sum(array_map(static fn(SectionHealth $one): int => $one->total, $health));
        $failing  = array_sum(array_map(static fn(SectionHealth $one): int => $one->failing, $health));
        $highOpen = [] !== array_filter($health, static fn(SectionHealth $one): bool => $one->highOpen);
        $broken   = [];
        foreach (IssueKind::cases() as $kind) {
            if (! $kind->siteWide() || ! array_key_exists($kind->value, $siteChecks)) {
                continue;
            }

            $total += $kind->tier()->weight();
            if (! $siteChecks[$kind->value]) {
                continue;
            }

            $failing  += $kind->tier()->weight();
            $highOpen = $highOpen || Tier::High === $kind->tier();
            $broken[] = $kind;
        }

        return new self(Score::of($total, $failing, $highOpen ? Tier::High : null), $health, $broken);
    }

    /**
     * What to fix, most important first: by tier, then by how many records it touches.
     *
     * @return list<IssueCount>
     */
    public function fixFirst(): array
    {
        $counts = [];
        foreach (IssueKind::cases() as $kind) {
            $issue = $this->countOf($kind);
            if (0 < $issue->count) {
                $counts[] = $issue;
            }
        }

        usort($counts, static function (IssueCount $a, IssueCount $b): int {
            $weights = $b->kind->tier()->weight() <=> $a->kind->tier()->weight();

            return 0 === $weights ? $b->count <=> $a->count : $weights;
        });

        return $counts;
    }

    /**
     * How many records have an issue of this kind, and in which sections; a site-wide kind counts once
     * while it fails.
     */
    private function countOf(IssueKind $kind): IssueCount
    {
        if ($kind->siteWide()) {
            return new IssueCount($kind, in_array($kind, $this->siteIssues, strict: true) ? 1 : 0);
        }

        $sections = [];
        foreach ($this->sections as $section) {
            if (0 === $section->count($kind)) {
                continue;
            }

            $sections[$section->section] = $section->count($kind);
        }

        return new IssueCount($kind, array_sum($sections), $sections);
    }
}

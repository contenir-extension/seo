<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Health;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Health\SiteHealth;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\TitleTaken;
use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SiteHealthTest extends TestCase
{
    #[Test]
    public function countsASiteWideFailureAsAHighImpactIssueThatKeepsTheSiteFromGood(): void
    {
        $health = $this->site([$this->section('site.page', [])], sitemapOff: true);

        static::assertSame([89, [IssueKind::SitemapOff]], [$health->score->value, $health->siteIssues]);
    }

    #[Test]
    public function hasNoScoreWhenNothingCouldBeChecked(): void
    {
        static::assertNull(SiteHealth::of([], [])->score->value);
    }

    #[Test]
    public function leavesOutASiteWideCheckThatDoesNotApply(): void
    {
        $health = SiteHealth::of([$this->section('site.page', [])], []);

        static::assertSame(100, $health->score->value);
    }

    #[Test]
    public function listsWhatToFixByTierThenByHowManyRecordsItTouches(): void
    {
        $health = $this->site(
            [
                $this->section('site.page', [
                    $this->record('site.page', 1, [$this->absent('description')]),
                    $this->record('site.page', 2, [
                        $this->absent('description'),
                        new MetaIssue('discoverable', 'switch', 'Hidden.'),
                    ]),
                    $this->record('site.page', 3, [new MetaIssue('title', 'title', TitleTaken::MESSAGE)]),
                    $this->record('site.page', 4, [new MetaIssue('description', 'summary', 'Short.')]),
                ]),
            ],
            sitemapOff: true,
        );

        $found = [];
        foreach ($health->fixFirst() as $issue) {
            $found[$issue->kind->value] = $issue->count;
        }

        static::assertSame(
            [
                'missing-description' => 2,
                'duplicate-title'     => 1,
                'sitemap-off'         => 1,
                'description-length'  => 1,
                'hidden'              => 1,
            ],
            $found,
        );
    }

    #[Test]
    public function saysWhichSectionsHaveEachIssue(): void
    {
        $health = $this->site([
            $this->section('site.page', [$this->record('site.page', 1, [$this->absent('description')])]),
            $this->section('library.project', [
                $this->record('library.project', 1, [$this->absent('description')]),
                $this->record('library.project', 2, [$this->absent('description')]),
            ]),
        ]);

        static::assertSame(['site.page' => 1, 'library.project' => 2], $health->fixFirst()[0]->sections);
    }

    #[Test]
    public function scoresACleanSiteAHundred(): void
    {
        $health = $this->site([$this->section('site.page', [])]);

        static::assertSame([100, 'Good', []], [$health->score->value, $health->score->label(), $health->siteIssues]);
    }

    #[Test]
    public function scoresEachSectionAndCapsTheSiteWhileAnySectionHasAHighImpactIssue(): void
    {
        $health = $this->site([
            $this->section('site.page', []),
            $this->section('library.project', [$this->record('library.project', 1, [$this->absent('title')])]),
        ]);

        static::assertSame(
            [100, 89, 89],
            [$health->sections[0]->score->value, $health->sections[1]->score->value, $health->score->value],
        );
    }

    private function absent(string $role): MetaIssue
    {
        return new MetaIssue($role, $role, 'Add one.', missing: true);
    }

    /**
     * @param non-empty-list<MetaIssue> $issues
     */
    private function record(string $section, int $id, array $issues): RecordIssues
    {
        return new RecordIssues(new RecordReference($section, $id), "Record {$id}", $issues);
    }

    /**
     * @param list<RecordIssues> $records
     */
    private function section(string $key, array $records): SectionIssues
    {
        return new SectionIssues($key, $key, 10, $records, 0);
    }

    /**
     * @param list<SectionIssues> $sections
     */
    private function site(array $sections, bool $sitemapOff = false): SiteHealth
    {
        return SiteHealth::of($sections, ['sitemap-off' => $sitemapOff, 'defaults-not-set' => false]);
    }
}

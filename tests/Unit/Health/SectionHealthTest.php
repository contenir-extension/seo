<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Health;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Health\SectionHealth;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SectionHealthTest extends TestCase
{
    #[Test]
    public function checksASmallShareImageOnlyOfTheRecordsTheSiteRendered(): void
    {
        $health = SectionHealth::of($this->section([], inspected: 4));

        static::assertSame(434, $health->total);
    }

    #[Test]
    public function countsEachRecordOncePerKindOfIssue(): void
    {
        $health = SectionHealth::of($this->section([
            $this->record(1, [
                new MetaIssue('description', 'summary', 'Too short.'),
                new MetaIssue('description', 'summary', 'Too short again.'),
                new MetaIssue('title', 'title', 'Add a title.', missing: true),
            ]),
            $this->record(2, [new MetaIssue('description', 'summary', 'Too short.')]),
        ]));

        static::assertSame(
            [2, 1, 0],
            [
                $health->count(IssueKind::DescriptionLength),
                $health->count(IssueKind::MissingTitle),
                $health->count(IssueKind::Hidden),
            ],
        );
    }

    #[Test]
    public function doesNotCheckForWhatTheSectionDoesNotDeclare(): void
    {
        $declared = SectionHealth::of($this->section([], applies: ['title', 'description']));

        static::assertSame(380, $declared->total);
    }

    #[Test]
    public function hasNoScoreForASectionWithNoRecords(): void
    {
        $health = SectionHealth::of($this->section([], checked: 0));

        static::assertNull($health->score->value);
    }

    /**
     * Ten records, checked for title, description, image and hidden: per record the checks are worth
     * 10 + 10 + 10 + 4 + 4 + 4 + 1 = 43, so 430 in all, and one missing description is 10 of it, but
     * a high-impact issue is open, so it cannot be Good.
     */
    #[Test]
    public function keepsASectionWithAHighImpactIssueOutOfGood(): void
    {
        $health = SectionHealth::of($this->section([
            $this->record(1, [new MetaIssue('description', 'summary', 'Add one.', missing: true)]),
        ]));

        static::assertSame([89, 'Needs work', true, 10, 430], [
            $health->score->value,
            $health->score->label(),
            $health->highOpen,
            $health->failing,
            $health->total,
        ]);
    }

    #[Test]
    public function leavesASectionWithOnlyALowIssueGood(): void
    {
        $health = SectionHealth::of($this->section([
            $this->record(1, [new MetaIssue('discoverable', 'discoverable', 'Hidden.')]),
        ]));

        static::assertSame([99, 'Good', false], [$health->score->value, $health->score->label(), $health->highOpen]);
    }

    #[Test]
    public function scoresACleanSectionAHundred(): void
    {
        $health = SectionHealth::of($this->section([]));

        static::assertSame([100, 'Good', false], [$health->score->value, $health->score->label(), $health->highOpen]);
    }

    /**
     * @param non-empty-list<MetaIssue> $issues
     */
    private function record(int $id, array $issues): RecordIssues
    {
        return new RecordIssues(new RecordReference('site.page', $id), "Page {$id}", $issues);
    }

    /**
     * @param list<RecordIssues> $records
     * @param ?list<string>      $applies
     */
    private function section(
        array $records,
        int $checked = 10,
        int $inspected = 0,
        ?array $applies = null,
    ): SectionIssues {
        return new SectionIssues(
            'site.page',
            'Pages',
            $checked,
            $records,
            $inspected,
            $applies ?? ['title', 'description', 'image', 'discoverable'],
        );
    }
}

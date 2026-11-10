<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Module\RecordFilter;
use Contenir\Extension\Seo\Module\ReportRow;
use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;
use Contenir\Extension\Seo\Section\Hidden;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class RecordFilterTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    /**
     * @return array<string, array{array<string, string>, list<int>}>
     */
    public static function rowsProvider(): array
    {
        return [
            'no role or kind: what needs acting on' => [[], [1]],
            'a section alone'                       => [['section' => self::SECTION], [1]],
            'a role, advice included'               => [['role' => 'description'], [1, 2]],
            'the hidden, by role'                   => [['role' => 'discoverable'], [3]],
            'the hidden, by kind'                   => [['kind' => 'hidden'], [3]],
            'advice, by kind'                       => [['kind' => 'description-length'], [2]],
        ];
    }

    /**
     * A record with a missing description, one with a short description and one hidden from search.
     */
    private static function section(): SectionIssues
    {
        $record = static fn(int $id, MetaIssue $issue): RecordIssues => new RecordIssues(
            new RecordReference(self::SECTION, $id),
            "House {$id}",
            [$issue],
        );

        return new SectionIssues(
            self::SECTION,
            'Projects',
            3,
            [
                $record(1, new MetaIssue('description', 'description', 'Add a description.', missing: true)),
                $record(2, new MetaIssue('description', 'description', 'The description is 27 characters.')),
                $record(3, new MetaIssue('discoverable', 'visible', Hidden::BY_SWITCH)),
            ],
            0,
        );
    }

    #[Test]
    public function aKindAskedForShowsOnlyARecordsIssuesOfThatKind(): void
    {
        $short  = new MetaIssue('description', 'description', 'The description is 27 characters.');
        $record = new RecordIssues(new RecordReference(self::SECTION, 1), 'House', [
            new MetaIssue('title', 'title', 'Add a title.', missing: true),
            $short,
        ]);

        static::assertSame([$short], RecordFilter::from(['kind' => 'description-length'], [])->issuesOf($record));
    }

    #[Test]
    public function asksForASectionWhereverTheReportListsIt(): void
    {
        $sections = [new SectionIssues('library.news.article', 'News', 0, [], 0), self::section()];

        static::assertCount(1, RecordFilter::from(['section' => self::SECTION], $sections)->rows($sections));
    }

    /**
     * @param array<string, string> $query
     * @param list<int>             $ids
     */
    #[Test]
    #[DataProvider('rowsProvider')]
    public function asksForWhatNeedsActingOnUnlessARoleOrKindIsNamed(array $query, array $ids): void
    {
        $sections = [self::section()];

        static::assertSame($ids, array_map(
            static fn(ReportRow $row): int|string => $row->record->record->id,
            RecordFilter::from($query, $sections)->rows($sections),
        ));
    }

    #[Test]
    public function dropsAKindNobodyKnows(): void
    {
        static::assertNull(RecordFilter::from(['kind' => 'nope'], [])->kind);
    }

    #[Test]
    public function dropsAKindOfIssueAboutTheWholeSite(): void
    {
        static::assertNull(RecordFilter::from(['kind' => 'sitemap-off'], [])->kind);
    }

    #[Test]
    public function dropsASectionTheReportDidNotFind(): void
    {
        static::assertNull(RecordFilter::from(['section' => 'a.b.c'], [])->section);
    }

    #[Test]
    public function keepsAKindOfIssueAboutARecord(): void
    {
        static::assertSame(IssueKind::MissingTitle, RecordFilter::from(['kind' => 'missing-title'], [])->kind);
    }

    #[Test]
    public function keepsASectionTheReportFound(): void
    {
        static::assertSame(
            'a.b.c',
            RecordFilter::from(['section' => 'a.b.c'], [new SectionIssues('a.b.c', 'Pages', 1, [], 0)])->section,
        );
    }
}

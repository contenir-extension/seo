<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\RecordList\RecordList;
use Contenir\Extension\Api\RecordList\RecordListLink;
use Contenir\Extension\Api\Testing\InMemoryTemplates;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Module\RecordFilter;
use Contenir\Extension\Seo\Module\RecordLine;
use Contenir\Extension\Seo\Module\RecordsList;
use Contenir\Extension\Seo\Module\ReportRow;
use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class RecordsListTest extends TestCase
{
    private static function list(
        ?string $view,
        bool $bulk = false,
        ?RecordFilter $filter = null,
        int $page = 1,
        int $total = 1,
    ): RecordList {
        $section = new SectionIssues('library.projects.project', 'Projects', 1, [], 0);
        $record  = new RecordIssues(
            new RecordReference('library.projects.project', 7),
            'House',
            [new MetaIssue('title', 'title', 'Add a title.', missing: true)],
        );
        $line = new RecordLine(new ReportRow($section, $record), ['Add a title.'], '/admin/records/7', $view);

        return (new RecordsList($filter ?? RecordFilter::from([], []), [$line], $page, $total, $bulk))->of(
            new InMemoryTemplates(new Handle('seo'), '/nowhere'),
        );
    }

    #[Test]
    public function carriesTheFiltersWithTheBulkPost(): void
    {
        $filter = RecordFilter::from(['role' => 'title'], []);

        static::assertSame(
            ['role' => 'title'],
            self::list(
                view: null,
                bulk: true,
                filter: $filter,
            )->bulkValues,
        );
    }

    #[Test]
    public function leadsOnlyToThePreviewWhereTheRecordHasNoPage(): void
    {
        $links = self::list(view: null)->rows[0]->actions;

        static::assertSame(
            ['Preview in search'],
            array_map(static fn(RecordListLink $link): string => $link->label, $links),
        );
    }

    #[Test]
    public function leadsToThePreviewAndToThePageInANewTab(): void
    {
        $links = self::list(view: 'https://www.example.com/house')->rows[0]->actions;

        static::assertSame(
            [
                ['Preview in search', false],
                ['View the page',     true],
            ],
            array_map(static fn(RecordListLink $link): array => [$link->label, $link->newTab], $links),
        );
    }

    #[Test]
    public function linksThePreviewToTheRecordsSectionAndId(): void
    {
        static::assertSame(
            '/admin/extension/seo/preview?section=library.projects.project&id=7',
            self::list(view: null)->rows[0]->actions[0]->url,
        );
    }

    #[Test]
    public function offersEachFixAsABulkActionWhereTheHostCanApplyThem(): void
    {
        $list = self::list(
            view: null,
            bulk: true,
        );

        static::assertSame(
            [
                array_map(static fn(FixKind $fix): string => $fix->value, FixKind::cases()),
                '/admin/extension/seo/records-fix',
            ],
            [array_map(static fn($action): string => $action->value, $list->bulk), $list->bulkUrl],
        );
    }

    #[Test]
    public function offersNoBulkActionWhereTheHostCannotApplyThem(): void
    {
        $list = self::list(
            view: null,
            bulk: false,
        );

        static::assertSame([[], ''], [$list->bulk, $list->bulkUrl]);
    }

    #[Test]
    public function pagesKeepTheFilter(): void
    {
        $filter = RecordFilter::from(['role' => 'title'], []);
        $pager  = self::list(
            view: null,
            filter: $filter,
            page: 2,
            total: 120,
        )->pager;

        static::assertSame(
            [2, 50, 120, '/admin/extension/seo/records?role=title&page=3'],
            [$pager->page, $pager->perPage, $pager->total, ($pager->pageUrl)(3)],
        );
    }

    #[Test]
    public function setsTheSectionNameAsideAndLinksTheTitleToItsEditScreen(): void
    {
        $cells = self::list(view: null)->rows[0]->cells;

        static::assertSame(
            [
                ['House',        '/admin/records/7', false],
                ['Projects',     null,               true],
                ['Add a title.', null,               false],
            ],
            array_map(static fn($cell): array => [$cell->lines[0], $cell->href, $cell->muted], $cells),
        );
    }

    #[Test]
    public function tellsAnEmptyListItIsNothingToFix(): void
    {
        $list = self::list(view: null);

        static::assertSame(['Nothing to fix', 'No record matches these filters.'], [
            $list->emptyTitle,
            $list->emptyDescription,
        ]);
    }
}

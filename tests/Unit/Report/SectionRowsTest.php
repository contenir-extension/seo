<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Report;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Report\SearchReport;
use Contenir\Extension\Seo\Report\SectionRows;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\PagedSections;
use Contenir\Extension\Seo\Tests\TestAsset\Sections\OverridableSections;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[Group('unit')]
final class SectionRowsTest extends TestCase
{
    private static function block(): SectionBlock
    {
        return new SectionBlock('library.projects.project', new Element('seo'), ['title']);
    }

    #[Test]
    public function asksOnceMoreAfterAPageThatWasFull(): void
    {
        $sections = new PagedSections(total: SearchReport::PAGE);
        (new SectionRows($sections))->of(self::block());

        static::assertSame(2, $sections->calls);
    }

    #[Test]
    public function readsASectionTheSiteWillNotLendAsHavingNoRecords(): void
    {
        $sections = new OverridableSections(
            ['library.projects.project' => self::block()],
            unread: RowsRefusedException::column('library.projects.project', 'images'),
        );

        static::assertSame([], (new SectionRows($sections))->of(self::block()));
    }

    #[Test]
    public function readsEveryPublishedRecordPageByPage(): void
    {
        $sections = new PagedSections(total: 250);
        $rows     = (new SectionRows($sections))->of(self::block());

        static::assertSame([225, 3], [count($rows), $sections->calls]);
    }

    #[Test]
    public function stopsAfterAShortPage(): void
    {
        $sections = new PagedSections(total: 5);
        (new SectionRows($sections))->of(self::block());

        static::assertSame(1, $sections->calls);
    }

    #[Test]
    public function stopsAtTheMostRecordsItReads(): void
    {
        $sections = new PagedSections(total: 6000);
        $rows     = (new SectionRows($sections))->of(self::block());

        static::assertSame([4500, 50], [count($rows), $sections->calls]);
    }
}

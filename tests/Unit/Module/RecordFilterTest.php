<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Module\RecordFilter;
use Contenir\Extension\Seo\Report\SectionIssues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class RecordFilterTest extends TestCase
{
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

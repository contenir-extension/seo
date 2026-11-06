<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\SectionTitles;
use Contenir\Extension\Seo\Report\SectionIssues;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class SectionTitlesTest extends TestCase
{
    #[Test]
    public function mapsEachSectionToItsTitle(): void
    {
        static::assertSame(
            ['a.b.c' => 'Projects', 'd.e.f' => 'News', 'g.h.i' => 'Pages'],
            SectionTitles::of([
                new SectionIssues('a.b.c', 'Projects', 3, [], 0),
                new SectionIssues('d.e.f', 'News', 2, [], 0),
                new SectionIssues('g.h.i', 'Pages', 1, [], 0),
            ]),
        );
    }
}

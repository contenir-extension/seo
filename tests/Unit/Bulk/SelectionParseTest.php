<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\Selection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class SelectionParseTest extends TestCase
{
    #[Test]
    public function keepsEverythingAfterTheFirstBarInTheId(): void
    {
        static::assertEquals([new RecordReference('a.b.c', '3|4')], Selection::parse(['a.b.c|3|4']));
    }

    #[Test]
    public function keysARecordBySectionAndId(): void
    {
        static::assertSame('a.b.c|3', Selection::key(new RecordReference('a.b.c', 3)));
    }

    #[Test]
    public function leavesOutAKeyWithNoId(): void
    {
        static::assertSame([], Selection::parse(['a.b.c|']));
    }

    #[Test]
    public function leavesOutAKeyWithNoSection(): void
    {
        static::assertSame([], Selection::parse(['|3']));
    }

    #[Test]
    public function leavesOutKeysThatAreNotTextWithABar(): void
    {
        static::assertEquals(
            [new RecordReference('a.b.c', '3')],
            Selection::parse([5, ['a|b'], 'nobar', null, 'a.b.c|3']),
        );
    }

    #[Test]
    public function readsASectionAndAnIdFromEachKey(): void
    {
        static::assertEquals(
            [new RecordReference('a.b.c', '3'), new RecordReference('d.e.f', '7')],
            Selection::parse(['a.b.c|3', 'd.e.f|7']),
        );
    }
}

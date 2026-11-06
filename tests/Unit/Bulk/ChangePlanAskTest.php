<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\ChangePlan;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RecordingSections;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
#[Group('bulk')]
final class ChangePlanAskTest extends TestCase
{
    private static function sections(): RecordingSections
    {
        return new RecordingSections([], [
            'a.b.c' => new SectionBlock('a.b.c', new Element('seo'), ['title']),
            'd.e.f' => new SectionBlock('d.e.f', new Element('seo'), ['title']),
        ]);
    }

    #[Test]
    public function asksForARecordPickedTwiceOnlyOnce(): void
    {
        $sections = self::sections();
        (new ChangePlan($sections))->build(FixKind::HideFromSearch, [
            new RecordReference('a.b.c', 1),
            new RecordReference('a.b.c', 2),
            new RecordReference('a.b.c', 1),
        ]);

        static::assertSame([[1, 2]], array_map(static fn(array $asked): array => $asked[2], $sections->asked));
    }

    #[Test]
    public function asksOneSectionAtATimeForTheRecordsPickedInIt(): void
    {
        $sections = self::sections();
        (new ChangePlan($sections))->build(FixKind::HideFromSearch, [
            new RecordReference('a.b.c', 1),
            new RecordReference('d.e.f', 5),
            new RecordReference('a.b.c', 2),
        ]);

        static::assertSame(
            [['a.b.c', [1, 2]], ['d.e.f', [5]]],
            array_map(static fn(array $asked): array => [$asked[0], $asked[2]], $sections->asked),
        );
    }
}

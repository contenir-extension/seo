<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Strategy;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Strategy\SectionStrategy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SectionStrategyTest extends TestCase
{
    private static function strategy(string $xml): SectionStrategy
    {
        return SectionStrategy::of(new SectionBlock('a.b.c', Element::parse($xml), ['title']), 'Pages');
    }

    #[Test]
    public function aBlockNamingNothingTakesTheConventionsAndNoSchemaType(): void
    {
        $strategy = self::strategy('<seo type="page"/>');

        static::assertSame(
            ['', ['meta_title', 'title'], '', true],
            [
                $strategy->schema,
                $strategy->sourcesOf('title'),
                $strategy->altSource() === 'title' ? '' : 'x',
                $strategy->automatic,
            ],
        );
    }

    #[Test]
    public function aManualSectionDoesNotListContentAmongTheSources(): void
    {
        static::assertSame(
            'meta_description, description',
            self::strategy('<seo type="page" mode="manual"/>')->sourcesLine('description'),
        );
    }

    #[Test]
    public function aRoleThatTakesContentSaysSoAfterItsSourcesWhereTheSectionIsAutomatic(): void
    {
        static::assertSame(
            ['meta_description, description, From content', 'From content'],
            [
                self::strategy('<seo type="page"/>')->sourcesLine('description'),
                self::strategy('<seo type="page"/>')->sourcesLine('image'),
            ],
        );
    }

    #[Test]
    public function aRoleWithNoSourcesReadsAsNone(): void
    {
        static::assertSame('None', self::strategy('<seo type="page" mode="manual"/>')->sourcesLine('image'));
    }

    #[Test]
    public function readsASectionsBlockAsItsStrategy(): void
    {
        $strategy = self::strategy(
            '<seo type="page" schema="WebPage" mode="manual"><image alt="alt_text">hero</image></seo>',
        );

        static::assertSame(
            ['WebPage', false, ['hero'], 'alt_text'],
            [$strategy->schema, $strategy->automatic, $strategy->sourcesOf('image'), $strategy->altSource()],
        );
    }
}

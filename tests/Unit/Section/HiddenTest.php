<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Seo\Section\Hidden;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class HiddenTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, bool, ?string}>
     */
    public static function reasonProvider(): array
    {
        return [
            'shown'                             => [['visible' => '1'], false, null],
            'its switch off'                    => [['visible' => '0'], false, Hidden::BY_SWITCH],
            'robots of its own'                 => [
                ['seo' => '{"robots":"noindex, follow"}'],
                false,
                Hidden::BY_ROBOTS,
            ],
            'robots of its own set to none'     => [['seo' => '{"robots":"none"}'], false, Hidden::BY_ROBOTS],
            'robots that only stop links'       => [['seo' => '{"robots":"index, nofollow"}'], false, null],
            'an advanced field that is no JSON' => [['seo' => 'noindex'], false, null],
            'its page keeping search out'       => [['visible' => '1'], true, Hidden::BY_PAGE],
            'the switch named before robots'    => [
                ['visible' => '0', 'seo' => '{"robots":"noindex"}'],
                true,
                Hidden::BY_SWITCH,
            ],
            'robots named before the page'      => [['seo' => '{"robots":"noindex"}'], true, Hidden::BY_ROBOTS],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function switchProvider(): array
    {
        return [
            'the text 0'          => [['visible' => '0'], true],
            'the number 0'        => [['visible' => 0], true],
            'false'               => [['visible' => false], true],
            'empty text'          => [['visible' => ''], true],
            'only space'          => [['visible' => '  '], true],
            'the text 1'          => [['visible' => '1'], false],
            'the number 1'        => [['visible' => 1], false],
            'true'                => [['visible' => true], false],
            'nothing held'        => [['visible' => null], false],
            'a column never read' => [[], false],
        ];
    }

    #[Test]
    public function readsRobotsOnlyWhereTheBlockNamesAnAdvancedColumn(): void
    {
        static::assertNull(Hidden::reason(new SeoBlock('project'), ['seo' => '{"robots":"noindex"}']));
    }

    /**
     * @param array<string, mixed> $values
     */
    #[Test]
    #[DataProvider('switchProvider')]
    public function readsTheSwitchAsOffOnlyWhenItHoldsAnOffValue(array $values, bool $off): void
    {
        static::assertSame($off, Hidden::off(new SeoBlock('project'), $values));
    }

    /**
     * @param array<string, mixed> $values
     */
    #[Test]
    #[DataProvider('reasonProvider')]
    public function saysWhyARecordIsHidden(array $values, bool $noindex, ?string $reason): void
    {
        static::assertSame($reason, Hidden::reason(new SeoBlock('project', advanced: 'seo'), $values, $noindex));
    }

    #[Test]
    public function takesTheFirstColumnOfTheDiscoverableRole(): void
    {
        $seo = new SeoBlock('project', roles: ['discoverable' => ['searchable', 'visible']]);

        static::assertSame(
            ['searchable', Hidden::BY_SWITCH],
            [Hidden::column($seo), Hidden::reason($seo, ['searchable' => '0', 'visible' => '1'])],
        );
    }

    #[Test]
    public function takesVisibleWhereTheBlockNamesNoSwitch(): void
    {
        static::assertSame('visible', Hidden::column(new SeoBlock('project')));
    }
}

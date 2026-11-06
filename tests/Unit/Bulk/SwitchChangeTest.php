<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Bulk\SwitchChange;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class SwitchChangeTest extends TestCase
{
    /**
     * @return array<string, array{FixKind, string|int|null, array{string, ?string, string, ?string}|null}>
     */
    public static function switches(): array
    {
        return [
            'hide what is on'             => [FixKind::HideFromSearch, '1', ['visible', '1', '0', null]],
            'hide what is off'            => [FixKind::HideFromSearch, '0', null],
            'hide what holds nothing'     => [FixKind::HideFromSearch, null, null],
            'show what is off'            => [FixKind::ShowInSearch, '0', ['visible', '0', '1', null]],
            'show what is on'             => [FixKind::ShowInSearch, '1', null],
            'show what holds nothing'     => [FixKind::ShowInSearch, null, ['visible', null, '1', null]],
            'show what holds only space'  => [FixKind::ShowInSearch, '  ', ['visible', null, '1', null]],
            'hide what holds only space'  => [FixKind::HideFromSearch, '  ', null],
            'a number counts as its text' => [FixKind::HideFromSearch, 1, ['visible', '1', '0', null]],
        ];
    }

    /**
     * @param list<string> $fields
     */
    private static function block(array $fields): SectionBlock
    {
        return new SectionBlock('library.projects.project', new Element('seo'), $fields);
    }

    private static function row(string|int|null $held): RecordRow
    {
        return new RecordRow(new RecordReference('library.projects.project', 1), RecordStatus::Published, [
            'visible' => $held,
        ]);
    }

    private static function seo(): SeoBlock
    {
        return new SeoBlock('project', roles: ['discoverable' => ['visible']]);
    }

    #[Test]
    public function doesNothingForASectionWithNoDiscoverableRole(): void
    {
        $seo = new SeoBlock('project');

        static::assertNull(SwitchChange::of(FixKind::HideFromSearch, self::block(['visible']), $seo, self::row('1')));
    }

    #[Test]
    public function doesNothingForASectionWithoutTheSwitchColumn(): void
    {
        static::assertNull(SwitchChange::of(
            FixKind::HideFromSearch,
            self::block(['title']),
            self::seo(),
            self::row('1'),
        ));
    }

    /**
     * @param array{string, ?string, string, ?string}|null $expected
     */
    #[Test]
    #[DataProvider('switches')]
    public function writesTheOppositeOfWhatTheSwitchHolds(FixKind $kind, string|int|null $held, ?array $expected): void
    {
        static::assertSame($expected, SwitchChange::of($kind, self::block(['visible']), self::seo(), self::row($held)));
    }
}

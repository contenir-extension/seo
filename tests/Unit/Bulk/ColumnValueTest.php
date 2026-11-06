<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\ColumnValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class ColumnValueTest extends TestCase
{
    /**
     * @return array<string, array{mixed, ?string}>
     */
    public static function readProvider(): array
    {
        return [
            'nothing stored'      => [null, null],
            'a blank text'        => ['  ', null],
            'a text'              => ['A hut.', 'A hut.'],
            'a number'            => [7, '7'],
            'an array is no text' => [['x'], null],
        ];
    }

    /**
     * @return array<string, array{mixed, ?string, ?string, ?string}>
     */
    public static function writeProvider(): array
    {
        return [
            'a plain text'               => ['Old.', null, 'New.', 'New.'],
            'a plain text cleared'       => ['Old.', null, null, ''],
            'a key set in nothing'       => [null, 'description', 'New.', '{"description":"New."}'],
            'a key set in a blank'       => ['', 'description', 'New.', '{"description":"New."}'],
            'a key set beside others'    => [
                '{"robots":"noindex"}',
                'description',
                'New.',
                '{"robots":"noindex","description":"New."}',
            ],
            'a key replaced'             => ['{"description":"Old."}', 'description', 'New.', '{"description":"New."}'],
            'a key removed'              => [
                '{"description":"Old.","robots":"noindex"}',
                'description',
                null,
                '{"robots":"noindex"}',
            ],
            'the last key removed'       => ['{"description":"Old."}', 'description', null, ''],
            'text kept as it is written' => [
                null,
                'description',
                'Café & "bar" / more',
                '{"description":"Café & \\"bar\\" / more"}',
            ],
            'something that is not JSON' => ['not json', 'description', 'New.', null],
        ];
    }

    #[Test]
    public function readsNoTextUnderAKeyOfSomethingThatIsNotJson(): void
    {
        static::assertNull(ColumnValue::read('not json', 'description'));
    }

    #[Test]
    #[DataProvider('readProvider')]
    public function readsTheTextAStoredValueHolds(mixed $stored, ?string $expected): void
    {
        static::assertSame($expected, ColumnValue::read($stored, null));
    }

    #[Test]
    public function readsTheTextUnderAKey(): void
    {
        static::assertSame('A hut.', ColumnValue::read('{"description":"A hut.","robots":"noindex"}', 'description'));
    }

    #[Test]
    #[DataProvider('writeProvider')]
    public function storesATextWhereItIsToGo(mixed $stored, ?string $key, ?string $text, ?string $expected): void
    {
        static::assertSame($expected, ColumnValue::write($stored, $key, $text));
    }
}

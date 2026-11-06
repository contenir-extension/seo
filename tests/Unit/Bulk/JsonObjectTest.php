<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\JsonObject;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class JsonObjectTest extends TestCase
{
    /**
     * @return array<string, array{mixed, array<string, mixed>|null}>
     */
    public static function stored(): array
    {
        return [
            'nothing'       => [null, []],
            'an empty text' => ['', []],
            'only space'    => ['   ', []],
            'an object'     => ['{"a":"1","b":"2"}', ['a' => '1', 'b' => '2']],
            'not json'      => ['nope', null],
            'a number'      => [5, null],
            'a json scalar' => ['"text"', null],
        ];
    }

    #[Test]
    public function encodesAnEmptyObjectAsNothingAtAll(): void
    {
        static::assertSame('', JsonObject::encode([]));
    }

    #[Test]
    public function encodesTextAsWrittenWithoutEscapingIt(): void
    {
        static::assertSame('{"a":"Café / more"}', JsonObject::encode(['a' => 'Café / more']));
    }

    /**
     * @param array<string, mixed>|null $expected
     */
    #[Test]
    #[DataProvider('stored')]
    public function readsTheObjectAStoredValueHolds(mixed $stored, ?array $expected): void
    {
        static::assertSame($expected, JsonObject::of($stored));
    }

    #[Test]
    public function setsAKeyBesideTheOthers(): void
    {
        static::assertSame(['a' => '1', 'b' => '2', 'c' => '3'], JsonObject::with(['a' => '1', 'b' => '2'], 'c', '3'));
    }

    #[Test]
    public function takesAKeyOutLeavingTheOthers(): void
    {
        static::assertSame(['a' => '1', 'c' => '3'], JsonObject::with(['a' => '1', 'b' => '2', 'c' => '3'], 'b', null));
    }
}

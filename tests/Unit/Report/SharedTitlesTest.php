<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Report;

use Contenir\Extension\Seo\Report\SharedTitles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SharedTitlesTest extends TestCase
{
    /**
     * @return array<string, array{list<string|null>, string|null, bool}>
     */
    public static function titleProvider(): array
    {
        return [
            'held twice'                   => [['House', 'House'], 'House', true],
            'held twice in another case'   => [['HOUSE', 'house'], 'House', true],
            'held twice beyond ASCII'      => [['ÉTÉ', 'été'], 'Été', true],
            'held once'                    => [['House', 'Hut'], 'House', false],
            'not held'                     => [['House'], 'Hut', false],
            'no title'                     => [[null, null], null, false],
            'records without a title skip' => [[null, 'House', null], 'House', false],
        ];
    }

    /**
     * @param list<string|null> $titles
     */
    #[Test]
    #[DataProvider('titleProvider')]
    public function findsTitlesMoreThanOneRecordHolds(array $titles, ?string $title, bool $shared): void
    {
        static::assertSame($shared, (new SharedTitles($titles))->has($title));
    }
}

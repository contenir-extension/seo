<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Health;

use Contenir\Extension\Seo\Health\Age;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AgeTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function ages(): array
    {
        return [
            'a time in the future'   => [-5, '0 seconds'],
            'no time at all'         => [0, '0 seconds'],
            'one second'             => [1, '1 second'],
            'two seconds'            => [2, '2 seconds'],
            'a few seconds'          => [5, '5 seconds'],
            'a minute'               => [60, '60 seconds'],
            'just under two minutes' => [119, '119 seconds'],
            'two minutes'            => [120, '2 minutes'],
            'two and a half minutes' => [150, '2 minutes'],
            'just under two hours'   => [7199, '119 minutes'],
            'two hours'              => [7200, '2 hours'],
            'just under two days'    => [172_799, '47 hours'],
            'two days'               => [172_800, '2 days'],
            'many days'              => [864_000, '10 days'],
        ];
    }

    #[Test]
    #[DataProvider('ages')]
    public function saysHowLongAgoInWholeUnits(int $seconds, string $expected): void
    {
        static::assertSame($expected, Age::of($seconds));
    }
}

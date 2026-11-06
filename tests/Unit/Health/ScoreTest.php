<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Health;

use Contenir\Extension\Seo\Health\Score;
use Contenir\Extension\Seo\Health\Tier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class ScoreTest extends TestCase
{
    /**
     * @return array<string, array{int, int, ?Tier, ?int, string, string}>
     */
    public static function scoreProvider(): array
    {
        return [
            'nothing to check'             => [0, 0, null, null, '', ''],
            'nothing wrong'                => [430, 0, null, 100, 'Good', 'success'],
            'a little wrong, rounded down' => [430, 1, null, 99, 'Good', 'success'],
            'exactly good'                 => [100, 10, null, 90, 'Good', 'success'],
            'just short of good'           => [100, 11, null, 89, 'Needs work', 'warning'],
            'half'                         => [100, 50, null, 50, 'Needs work', 'warning'],
            'just under half'              => [100, 51, null, 49, 'Poor', 'destructive'],
            'everything wrong'             => [100, 100, null, 0, 'Poor', 'destructive'],
            'a high issue caps a good one' => [430, 20, Tier::High, 89, 'Needs work', 'warning'],
            'a cap never raises a score'   => [100, 60, Tier::High, 40, 'Poor', 'destructive'],
            'a medium issue does not cap'  => [430, 20, Tier::Medium, 95, 'Good', 'success'],
            'a low issue does not cap'     => [430, 1, Tier::Low, 99, 'Good', 'success'],
        ];
    }

    /**
     * @mago-expect lint:excessive-parameter-list One parameter per column of the data set.
     */
    #[DataProvider('scoreProvider')]
    #[Test]
    public function scoresTheShareOfWeightedChecksThatPass(
        int $total,
        int $failing,
        ?Tier $worst,
        ?int $value,
        string $label,
        string $tone,
    ): void {
        $score = Score::of($total, $failing, $worst);

        static::assertSame([$value, $label, $tone], [$score->value, $score->label(), $score->tone()]);
    }
}

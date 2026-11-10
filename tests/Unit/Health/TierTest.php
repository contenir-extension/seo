<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Health;

use Contenir\Extension\Seo\Health\Tier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class TierTest extends TestCase
{
    /**
     * @return array<string, array{Tier, bool}>
     */
    public static function tierProvider(): array
    {
        return [
            'high'   => [Tier::High, true],
            'medium' => [Tier::Medium, true],
            'low'    => [Tier::Low, false],
        ];
    }

    #[Test]
    #[DataProvider('tierProvider')]
    public function asksForAttentionForAllButLowIssues(Tier $tier, bool $attention): void
    {
        static::assertSame($attention, $tier->needsAttention());
    }
}

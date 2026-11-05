<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Clock;

use DateTimeImmutable;
use Override;
use Psr\Clock\ClockInterface;

/**
 * A clock that moves on a set number of seconds each time it is read.
 */
final class TickingClock implements ClockInterface
{
    private int $seconds = 0;

    public function __construct(
        private readonly int $step,
    ) {}

    #[Override]
    public function now(): DateTimeImmutable
    {
        $this->seconds += $this->step;

        return (new DateTimeImmutable('2026-11-05T09:00:00+00:00'))->modify("+{$this->seconds} seconds");
    }
}

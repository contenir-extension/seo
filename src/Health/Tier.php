<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

/**
 * How much an issue matters to how the site reads in search, and so how much of the score it takes: a
 * missing title is worth ten small ones.
 */
enum Tier: string
{
    case High   = 'high';
    case Medium = 'medium';
    case Low    = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }

    public function weight(): int
    {
        return match ($this) {
            self::High => 10,
            self::Medium => 4,
            self::Low => 1,
        };
    }
}

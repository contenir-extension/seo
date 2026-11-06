<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

use function intdiv;
use function min;

/**
 * A 0 to 100 score for how a part of the site reads in search: the share of what could be checked that
 * passes, each check counting for its tier (high 10, medium 4, low 1). It is rounded down, so 100 means
 * nothing is wrong, and while any high-impact issue is open it cannot reach Good. Null when there was
 * nothing to check.
 */
final readonly class Score
{
    /** The lowest score that is Good. */
    public const int GOOD = 90;

    /** The lowest score that is Needs work. */
    public const int NEEDS_WORK = 50;

    /** The most a part with a high-impact issue open can score. */
    public const int CAP = 89;

    public function __construct(
        public ?int $value,
    ) {}

    /**
     * @param int   $total   What the checks that apply are worth in all
     * @param int   $failing What the failing ones are worth
     * @param ?Tier $worst   The most important tier with an issue open, null when none is
     */
    public static function of(int $total, int $failing, ?Tier $worst = null): self
    {
        if (0 >= $total) {
            return new self(null);
        }

        $value = intdiv(($total - $failing) * 100, $total);

        return new self(Tier::High === $worst ? min($value, self::CAP) : $value);
    }

    /**
     * Good, Needs work or Poor; empty when there was nothing to check.
     */
    public function label(): string
    {
        return match (true) {
            null === $this->value => '',
            $this->value >= self::GOOD => 'Good',
            $this->value >= self::NEEDS_WORK => 'Needs work',
            default => 'Poor',
        };
    }

    /**
     * The tier of colour to show it in: success, warning or destructive; empty when it has no score.
     */
    public function tone(): string
    {
        return match ($this->label()) {
            'Good'       => 'success',
            'Needs work' => 'warning',
            'Poor'       => 'destructive',
            default      => '',
        };
    }
}

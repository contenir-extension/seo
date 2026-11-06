<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

use function intdiv;
use function max;
use function sprintf;

/**
 * How long ago something happened, for people: "40 seconds", "25 minutes", "3 hours", "2 days".
 */
final class Age
{
    private const int MINUTE = 60;
    private const int HOUR   = 3600;
    private const int DAY    = 86_400;

    public static function of(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if (1 === $seconds) {
            return '1 second';
        }

        if ($seconds < (2 * self::MINUTE)) {
            return sprintf('%d seconds', $seconds);
        }

        $minutes = intdiv(
            num1: $seconds,
            num2: self::MINUTE,
        );
        if ($seconds < (2 * self::HOUR)) {
            return sprintf('%d minutes', $minutes);
        }

        $hours = intdiv(
            num1: $seconds,
            num2: self::HOUR,
        );
        if ($seconds < (2 * self::DAY)) {
            return sprintf('%d hours', $hours);
        }

        return sprintf('%d days', intdiv(
            num1: $seconds,
            num2: self::DAY,
        ));
    }
}

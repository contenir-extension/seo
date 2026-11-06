<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use function preg_match;
use function str_ends_with;
use function strtolower;
use function substr;
use function trim;

/**
 * What the report calls a section: the site names one of its records ("Page",
 * "News article"), and the report counts them, so it says "Pages".
 */
final class SectionNames
{
    public static function plural(string $name): string
    {
        $name  = trim($name);
        $lower = strtolower($name);

        return match (true) {
            str_ends_with($lower, 's') => $name,
            1 === preg_match('/[^aeiou]y$/', $lower) => substr($name, offset: 0, length: -1) . 'ies',
            default => "{$name}s",
        };
    }
}

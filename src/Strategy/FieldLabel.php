<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use function mb_strtolower;
use function preg_replace;
use function trim;
use function ucfirst;

/**
 * A column or schema name as an editor reads it: "meta_title" as "Meta title", "CreativeWork" as
 * "Creative work". Only what is shown changes; the name itself is what is saved.
 */
final class FieldLabel
{
    public static function of(string $name): string
    {
        $spaced = (string) preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', replacement: ' ', subject: $name);
        $spaced = (string) preg_replace('/[_\-\s]+/', replacement: ' ', subject: $spaced);

        return ucfirst(mb_strtolower(trim($spaced)));
    }
}

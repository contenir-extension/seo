<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use function array_count_values;
use function array_filter;
use function array_key_exists;
use function array_map;
use function is_string;
use function mb_strtolower;

/**
 * Which titles more than one record of a section holds, compared without case;
 * the titles are trimmed already.
 */
final readonly class SharedTitles
{
    /** @var array<array-key, int> */
    private array $shared;

    /**
     * @param list<string|null> $titles Each record's title, if it has one
     */
    public function __construct(array $titles)
    {
        $this->shared = array_filter(
            array_count_values(array_map(mb_strtolower(...), array_filter($titles, is_string(...)))),
            static fn(int $count): bool => $count > 1,
        );
    }

    public function has(?string $title): bool
    {
        return null !== $title && array_key_exists(mb_strtolower($title), $this->shared);
    }
}

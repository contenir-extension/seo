<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

/**
 * How a round of asking the site went.
 */
final readonly class Refreshed
{
    /**
     * @param int $checked Records the site answered for
     * @param int $unanswered Records it didn't
     * @param bool $finished Whether every record that needed asking was asked
     */
    public function __construct(
        public int $checked,
        public int $unanswered,
        public bool $finished,
    ) {}
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

/**
 * How many records have one kind of issue, and in which sections; a site-wide kind counts once.
 */
final readonly class IssueCount
{
    /**
     * @param array<string, int> $sections How many records of each section have it, by the section's key
     */
    public function __construct(
        public IssueKind $kind,
        public int $count,
        public array $sections = [],
    ) {}
}

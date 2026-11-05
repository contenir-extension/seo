<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use function array_filter;
use function array_values;
use function count;

/**
 * A section's records as the search report found them: how many it read, and
 * those that need something.
 */
final readonly class SectionIssues
{
    /**
     * @param string $section The section's key, such as "library.projects.project"
     * @param string $title What the site calls the section
     * @param int $checked The records read, at most SearchReport::MAX_ROWS
     * @param list<RecordIssues> $records The records with at least one issue
     */
    public function __construct(
        public string $section,
        public string $title,
        public int $checked,
        public array $records,
    ) {}

    public function count(?string $role): int
    {
        return count($this->in($role));
    }

    /**
     * Whether the section holds more records than the report reads.
     */
    public function cutShort(): bool
    {
        return $this->checked >= SearchReport::MAX_ROWS;
    }

    /**
     * The records with an issue in $role, or with any issue without one.
     *
     * @return list<RecordIssues>
     */
    public function in(?string $role): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(RecordIssues $record): bool => [] !== $record->in($role),
        ));
    }
}

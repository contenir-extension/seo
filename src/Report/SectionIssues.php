<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Seo\Health\IssueKind;

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
     * @param int $checked The published records read
     * @param list<RecordIssues> $records The records with at least one issue
     * @param int $inspected The records judged by what the site rendered, not their columns
     * @param list<string> $applies The roles the section's records can be checked for: title and description
     *                              always, image and discoverable when it declares them
     *
     * @mago-expect lint:excessive-parameter-list One parameter per thing a section's report says.
     */
    public function __construct(
        public string $section,
        public string $title,
        public int $checked,
        public array $records,
        public int $inspected,
        public array $applies = ['title', 'description', 'image', 'discoverable'],
    ) {}

    public function count(?string $role): int
    {
        return count($this->in($role));
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

    /**
     * The records with an issue of this kind.
     *
     * @return list<RecordIssues>
     */
    public function ofKind(IssueKind $kind): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(RecordIssues $record): bool => [] !== $record->ofKind($kind),
        ));
    }
}

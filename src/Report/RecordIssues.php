<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Listener\MetaIssue;

use function array_filter;
use function array_values;

/**
 * What a record of a section still needs for search, and what to call it.
 */
final readonly class RecordIssues
{
    /**
     * @param string $title The record's title, or its number when it has none
     * @param non-empty-list<MetaIssue> $issues
     */
    public function __construct(
        public RecordReference $record,
        public string $title,
        public array $issues,
    ) {}

    /**
     * Its issues in $role, or all of them without one.
     *
     * @return list<MetaIssue>
     */
    public function in(?string $role): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn(MetaIssue $issue): bool => null === $role || $role === $issue->role,
        ));
    }

    /**
     * Its issues of one kind, or all of them without one.
     *
     * @return list<MetaIssue>
     */
    public function ofKind(?IssueKind $kind): array
    {
        return array_values(array_filter(
            $this->issues,
            static fn(MetaIssue $issue): bool => null === $kind || $kind === IssueKind::of($issue),
        ));
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Seo\Report\SearchReport;
use Contenir\Extension\Seo\Report\SectionIssues;

use function array_filter;
use function array_key_exists;
use function is_string;

/**
 * Which of the report's records a screen asks for: one section's, one role's,
 * or all of them. A section or role the report doesn't know is ignored.
 */
final readonly class RecordFilter
{
    private function __construct(
        public ?string $section,
        public ?string $role,
    ) {}

    /**
     * @param array<array-key, mixed> $query The request's query values
     * @param list<SectionIssues> $sections
     */
    public static function from(array $query, array $sections): self
    {
        $section = self::text($query, 'section');
        $role    = self::text($query, 'role');
        $known   = array_filter($sections, static fn(SectionIssues $found): bool => $found->section === $section);

        return new self(
            [] === $known ? null : $section,
            null !== $role && array_key_exists($role, SearchReport::ROLES) ? $role : null,
        );
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function text(array $query, string $key): ?string
    {
        return is_string($query[$key] ?? null) ? $query[$key] : null;
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter(['section' => $this->section, 'role' => $this->role], is_string(...));
    }

    /**
     * The records it asks for, a section's together.
     *
     * @param list<SectionIssues> $sections
     *
     * @return list<ReportRow>
     */
    public function rows(array $sections): array
    {
        $rows = [];
        foreach ($sections as $section) {
            if (null !== $this->section && $this->section !== $section->section) {
                continue;
            }

            foreach ($section->in($this->role) as $record) {
                $rows[] = new ReportRow($section, $record);
            }
        }

        return $rows;
    }
}

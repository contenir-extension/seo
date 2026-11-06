<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Module\RecordFilter;
use Contenir\Extension\Seo\Module\ReportRow;
use Contenir\Extension\Seo\Report\SectionIssues;

use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_array;
use function is_string;
use function sprintf;
use function str_contains;

/**
 * The records a bulk fix is for: the ones ticked on the Records screen, or every record the screen's filters
 * match. A record travels in a form as its section and id, "section|id".
 */
final class Selection
{
    /** Every record the filters match, rather than the ones ticked. */
    public const string ALL = 'all';

    public static function key(RecordReference $record): string
    {
        return sprintf('%s|%s', $record->section, $record->id);
    }

    /**
     * @param array<array-key, mixed> $body     The form's values
     * @param list<SectionIssues>     $sections What the report found
     *
     * @return list<RecordReference>
     */
    public static function of(array $body, array $sections): array
    {
        if (self::ALL === ($body['scope'] ?? null)) {
            return array_map(
                static fn(ReportRow $row): RecordReference => $row->record->record,
                RecordFilter::from($body, $sections)->rows($sections),
            );
        }

        return self::parse(is_array($body['records'] ?? null) ? array_values($body['records']) : []);
    }

    /**
     * @param list<mixed> $keys
     *
     * @return list<RecordReference>
     */
    public static function parse(array $keys): array
    {
        $records = [];
        foreach (array_filter(
            $keys,
            static fn(mixed $key): bool => is_string($key) && str_contains($key, '|'),
        ) as $key) {
            $parts   = explode('|', (string) $key, limit: 2);
            $section = $parts[0];
            $id      = $parts[1] ?? '';
            if ('' !== $section && '' !== $id) {
                $records[] = new RecordReference($section, $id);
            }
        }

        return $records;
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\SectionsInterface;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function sprintf;

/**
 * What the records of some changes hold now in the columns the changes are to, as the host has them saved.
 */
final readonly class CurrentValues
{
    public function __construct(
        private SectionsInterface $sections,
    ) {}

    /**
     * Where a change's column value is found in what of() answers.
     */
    public static function key(RunChange $change): string
    {
        return sprintf('%s|%s|%s', $change->record->section, $change->record->id, $change->column);
    }

    /**
     * @param list<RunChange> $changes
     *
     * @return array<string, mixed> Each column's saved value, by CurrentValues::key()
     *
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    public function of(array $changes): array
    {
        $bySection = [];
        foreach ($changes as $change) {
            $bySection[$change->record->section][] = $change;
        }

        $values = [];
        foreach ($bySection as $section => $sectionChanges) {
            $rows = $this->sections->rows(
                $section,
                array_values(array_unique(array_map(
                    static fn(RunChange $each): string => $each->column,
                    $sectionChanges,
                ))),
                array_map(static fn(RunChange $each): string|int => $each->record->id, $sectionChanges),
                count($sectionChanges),
            );
            foreach ($rows as $row) {
                foreach ($sectionChanges as $change) {
                    if ((string) $row->record->id !== (string) $change->record->id) {
                        continue;
                    }

                    $values[self::key($change)] = $row->value($change->column);
                }
            }
        }

        return $values;
    }
}

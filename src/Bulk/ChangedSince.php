<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionsInterface;

use function array_map;
use function array_unique;
use function array_values;
use function count;
use function is_scalar;

/**
 * Which of a run's saved changes are still as the run left them. A record changed since by someone is left as
 * it is, so an undo never writes over newer work.
 */
final readonly class ChangedSince
{
    public const string MESSAGE = 'Changed since it was applied';

    public function __construct(
        private SectionsInterface $sections,
    ) {}

    /**
     * The run's changes, with each saved change whose record no longer holds what the run wrote marked skipped.
     *
     * @return list<RunChange>
     *
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    public function mark(Run $run): array
    {
        $held = $this->held($run);

        return array_map(
            static fn(RunChange $change): RunChange => RunChange::DONE === $change->status
                && ! ($held[$change->record->section][(string) $change->record->id] ?? false)
                    ? $change->with(RunChange::SKIPPED, self::MESSAGE)
                    : $change,
            $run->changes,
        );
    }

    /**
     * @return array<string, array<string, bool>> Whether each saved change's record still holds the written value, by section and id
     *
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    private function held(Run $run): array
    {
        $saved = [];
        foreach ($run->changes as $change) {
            $saved[$change->record->section][] = $change;
        }

        $held = [];
        foreach ($saved as $section => $changes) {
            $rows = $this->sections->rows(
                $section,
                array_values(array_unique(array_map(static fn(RunChange $each): string => $each->column, $changes))),
                array_map(static fn(RunChange $each): string|int => $each->record->id, $changes),
                count($changes),
            );
            $held[$section] = $this->matches($changes, $rows);
        }

        return $held;
    }

    /**
     * @param list<RunChange> $changes
     * @param list<RecordRow> $rows
     *
     * @return array<string, bool>
     */
    private function matches(array $changes, array $rows): array
    {
        $matches = [];
        foreach ($rows as $row) {
            foreach ($changes as $change) {
                $value = $row->value($change->column);
                if ((string) $row->record->id === (string) $change->record->id) {
                    $matches[(string) $row->record->id] = is_scalar($value) && (string) $value === $change->after;
                }
            }
        }

        return $matches;
    }
}

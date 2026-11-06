<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\SectionsInterface;

use function array_filter;
use function array_map;
use function array_values;

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
        $saved = array_values(array_filter(
            $run->changes,
            static fn(RunChange $change): bool => RunChange::DONE === $change->status,
        ));
        $current = (new CurrentValues($this->sections))->of($saved);

        return array_map(
            static fn(RunChange $change): RunChange => RunChange::DONE === $change->status
                && ColumnValue::read($current[CurrentValues::key($change)] ?? null, $change->key) !== $change->after
                    ? $change->with(RunChange::SKIPPED, self::MESSAGE)
                    : $change,
            $run->changes,
        );
    }
}

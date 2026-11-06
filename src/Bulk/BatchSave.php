<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Closure;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Record\RecordUpdate;
use Contenir\Extension\Api\Record\RecordUpdateResult;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;

use function array_combine;
use function array_keys;
use function array_map;

/**
 * One call to the host's batched update for some of a run's changes, and what became of each.
 */
final readonly class BatchSave
{
    public function __construct(
        private RecordUpdatesInterface $updates,
    ) {}

    /**
     * @param array<int, RunChange>                             $chunk  The changes in this call, by their place in the run
     * @param array<int, string>                                $stored What each is to leave in its column, by the same places
     * @param Closure(RunChange, RecordUpdateResult): RunChange $settle What each change is once it has been saved
     *
     * @return array<int, RunChange> The changes as settled, by the same places
     *
     * @throws InvalidDeclarationException When a change names no field, or there are too many.
     */
    public function run(array $chunk, array $stored, Closure $settle): array
    {
        $results = $this->updates->update(array_map(
            static fn(int $place, RunChange $change): RecordUpdate => new RecordUpdate(
                $change->record,
                [$change->column => $stored[$place] ?? ''],
            ),
            array_keys($chunk),
            $chunk,
        ));

        return array_combine(array_keys($chunk), array_map($settle, $chunk, $results));
    }
}

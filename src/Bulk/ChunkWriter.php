<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Closure;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordUpdateResult;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Contenir\Extension\Api\Section\SectionsInterface;

use function array_diff_key;
use function array_filter;
use function array_intersect_key;
use function array_map;
use function array_values;

/**
 * Saves some of a run's changes in one call to the host, each as the text it is to leave in its column, set into
 * the JSON a column holds under a key where the change has one. A change whose column holds something that is not
 * JSON is held back rather than written over.
 */
final readonly class ChunkWriter
{
    public const string UNREADABLE = 'Its SEO settings could not be read, so they were left alone';

    public function __construct(
        private RecordUpdatesInterface $updates,
        private SectionsInterface $sections,
    ) {}

    /**
     * @param array<int, RunChange>                             $chunk  The changes in this call, by their place in the run
     * @param Closure(RunChange): ?string                       $text   What each leaves in its place; null to clear it
     * @param Closure(RunChange, RecordUpdateResult): RunChange $settle What each change is once it has been saved
     *
     * @return array<int, RunChange> The changes as settled, by the same places
     *
     * @throws InvalidDeclarationException When a change names no field, or there are too many.
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    public function run(array $chunk, Closure $text, Closure $settle): array
    {
        $current = (new CurrentValues($this->sections))->of(array_values($chunk));
        $stored  = array_map(
            static fn(RunChange $change): ?string => ColumnValue::write(
                $current[CurrentValues::key($change)] ?? null,
                $change->key,
                $text($change),
            ),
            $chunk,
        );
        $writable = array_filter($stored, static fn(?string $value): bool => null !== $value);
        $held     = array_map(
            static fn(RunChange $change): RunChange => $change->with(RunChange::STOPPED, self::UNREADABLE),
            array_diff_key($chunk, $writable),
        );

        /** @var array<int, string> $writable */
        return $held + (new BatchSave($this->updates))->run(array_intersect_key($chunk, $writable), $writable, $settle);
    }
}

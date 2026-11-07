<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordUpdateResult;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Contenir\Extension\Api\Section\SectionsInterface;

/**
 * Saves one description change through the host's record updates, as a run's changes are saved, and says what
 * became of it.
 */
final readonly class DescriptionWrite
{
    public function __construct(
        private RecordUpdatesInterface $updates,
        private SectionsInterface $sections,
    ) {}

    /**
     * @throws InvalidDeclarationException When the change names no field.
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public function run(Change $change): RunChange
    {
        $planned = RunChange::of($change);

        return (
            (new ChunkWriter($this->updates, $this->sections))->run(
                [$planned],
                static fn(RunChange $each): string => $each->after,
                static fn(RunChange $each, RecordUpdateResult $result): RunChange => $each->with(
                    Outcomes::status($result->result),
                    Outcomes::reason($result->result),
                ),
            )[0] ?? $planned
        );
    }
}

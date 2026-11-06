<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Queue\QueuedTaskInterface;
use Contenir\Extension\Api\Record\RecordUpdateResult;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;

use function array_chunk;
use function array_filter;
use function array_replace;
use function array_values;
use function is_string;

/**
 * Puts back what a run replaced, within a day of it: each value the run saved goes back to what it held
 * before, unless the record has been changed since, in which case it is left as it is.
 */
final readonly class UndoChanges implements QueuedTaskInterface
{
    public const string NAME = 'undo';

    private static function settle(RunChange $change, RecordUpdateResult $result): RunChange
    {
        return $result->succeeded()
            ? $change->with(RunChange::UNDONE)
            : $change->with(Outcomes::status($result->result), Outcomes::reason($result->result));
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ContainerExceptionInterface When the host's record updates can't be reached.
     * @throws InvalidDeclarationException When a change names no field.
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    #[Override]
    public function run(array $payload, ExtensionContextInterface $context): void
    {
        $store = RunStore::at($context->sitePath());
        $id    = $payload['run'] ?? null;
        $run   = is_string($id) ? $store->find($id) : null;
        $now   = $context->clock()->now()->getTimestamp();
        if (null === $run || ! $run->undoable($now)) {
            return;
        }

        /** @var RecordUpdatesInterface $saver */
        $saver = $context->services()->get(RecordUpdatesInterface::class);

        $changes = (new ChangedSince($context->sections()))->mark($run);
        $saved   = array_filter($changes, static fn(RunChange $change): bool => RunChange::DONE === $change->status);
        $writer  = new ChunkWriter($saver, $context->sections());
        foreach (array_chunk($saved, RecordUpdatesInterface::MAX_RECORDS, preserve_keys: true) as $chunk) {
            $changes = array_replace($changes, $writer->run(
                $chunk,
                static fn(RunChange $change): ?string => $change->before,
                self::settle(...),
            ));
        }

        $store->put($run->with(array_values($changes), $run->state, $now));
    }
}

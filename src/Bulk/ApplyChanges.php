<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Queue\QueuedTaskInterface;
use Contenir\Extension\Api\Record\RecordUpdateResult;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Override;
use Psr\Container\ContainerExceptionInterface;

use function array_chunk;
use function array_filter;
use function array_key_exists;
use function array_replace;
use function array_values;
use function is_string;

/**
 * Applies a run's changes through the host's batched update, fifty records to a call, as the editor who asked,
 * keeping what each save said and the values it replaced as it goes, so a run that is cut short shows how far
 * it got. A change already settled is not made twice.
 */
final readonly class ApplyChanges implements QueuedTaskInterface
{
    public const string NAME = 'apply';

    private static function settle(RunChange $change, RecordUpdateResult $result): RunChange
    {
        $saved = $change->with(Outcomes::status($result->result), Outcomes::reason($result->result));

        return array_key_exists($change->column, $result->previous)
            ? $saved->withBefore(ColumnValue::read($result->previous[$change->column], $change->key))
            : $saved;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ContainerExceptionInterface When the host's record updates can't be reached.
     * @throws InvalidDeclarationException When a change names no field.
     */
    #[Override]
    public function run(array $payload, ExtensionContextInterface $context): void
    {
        $store = RunStore::at($context->sitePath());
        $id    = $payload['run'] ?? null;
        $run   = is_string($id) ? $store->find($id) : null;
        if (null === $run) {
            return;
        }

        /** @var RecordUpdatesInterface $saver */
        $saver = $context->services()->get(RecordUpdatesInterface::class);

        $writer  = new ChunkWriter($saver, $context->sections());
        $changes = $run->changes;
        $store->put($run->with($changes, Run::RUNNING));
        $waiting = array_filter($changes, static fn(RunChange $change): bool => RunChange::PENDING === $change->status);
        foreach (array_chunk($waiting, RecordUpdatesInterface::MAX_RECORDS, preserve_keys: true) as $chunk) {
            $changes = array_replace($changes, $writer->run(
                $chunk,
                static fn(RunChange $change): string => $change->after,
                self::settle(...),
            ));
            $store->put($run->with(array_values($changes), Run::RUNNING));
        }

        $store->put($run->with(array_values($changes), Run::FINISHED));
    }
}

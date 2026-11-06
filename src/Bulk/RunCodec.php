<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_int;
use function is_string;

/**
 * How a run is kept in the store's file and read back: an entry of another shape is not a run.
 */
final class RunCodec
{
    /**
     * @param array<array-key, mixed> $stored
     */
    public static function decode(array $stored): ?Run
    {
        /** @var mixed $id */
        $id = $stored['id'] ?? null;
        /** @var mixed $at */
        $at      = $stored['at'] ?? null;
        $kind    = FixKind::tryFrom(Stored::string($stored['kind'] ?? ''));
        $changes = is_array($stored['changes'] ?? null) ? $stored['changes'] : [];
        if (! is_string($id) || ! is_int($at) || null === $kind) {
            return null;
        }

        /** @var mixed $undone */
        $undone = $stored['undoneAt'] ?? null;

        return new Run(
            $id,
            $at,
            Stored::string($stored['editor'] ?? ''),
            $kind,
            array_values(array_filter(array_map(
                static fn(mixed $change): ?RunChange => is_array($change) ? ChangeCodec::decode($change) : null,
                $changes,
            ))),
            Stored::string($stored['state'] ?? Run::QUEUED),
            is_int($undone) ? $undone : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function encode(Run $run): array
    {
        return [
            'id'       => $run->id,
            'at'       => $run->at,
            'editor'   => $run->editor,
            'kind'     => $run->kind->value,
            'state'    => $run->state,
            'undoneAt' => $run->undoneAt,
            'changes'  => array_map(ChangeCodec::encode(...), $run->changes),
        ];
    }
}

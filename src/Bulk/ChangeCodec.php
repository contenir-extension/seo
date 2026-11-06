<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function is_scalar;
use function is_string;

/**
 * How one change of a run is kept and read back.
 */
final class ChangeCodec
{
    /**
     * @param array<array-key, mixed> $stored
     */
    public static function decode(array $stored): ?RunChange
    {
        $record = Stored::record($stored);
        /** @var mixed $column */
        $column = $stored['column'] ?? null;
        /** @var mixed $after */
        $after = $stored['after'] ?? null;
        /** @var mixed $before */
        $before = $stored['before'] ?? null;
        if (null === $record || ! is_string($column) || ! is_string($after)) {
            return null;
        }

        return new RunChange(
            $record,
            Stored::string($stored['title'] ?? ''),
            Stored::string($stored['sectionTitle'] ?? ''),
            $column,
            is_scalar($before) ? (string) $before : null,
            $after,
            Stored::string($stored['status'] ?? RunChange::PENDING),
            Stored::string($stored['message'] ?? ''),
            is_string($stored['key'] ?? null) ? $stored['key'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function encode(RunChange $change): array
    {
        return [
            'section'      => $change->record->section,
            'id'           => $change->record->id,
            'title'        => $change->title,
            'sectionTitle' => $change->sectionTitle,
            'column'       => $change->column,
            'key'          => $change->key,
            'before'       => $change->before,
            'after'        => $change->after,
            'status'       => $change->status,
            'message'      => $change->message,
        ];
    }
}

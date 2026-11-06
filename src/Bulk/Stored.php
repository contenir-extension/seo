<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Url\RecordReference;

use function is_int;
use function is_scalar;
use function is_string;

/**
 * Reading what the run store kept, which may be of another shape than a run writes.
 */
final class Stored
{
    /**
     * The record an entry names, or null when it names none.
     *
     * @param array<array-key, mixed> $stored
     */
    public static function record(array $stored): ?RecordReference
    {
        /** @var mixed $section */
        $section = $stored['section'] ?? null;
        /** @var mixed $id */
        $id = $stored['id'] ?? null;

        return is_string($section) && '' !== $section && (is_string($id) || is_int($id))
            ? new RecordReference($section, $id)
            : null;
    }

    public static function string(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}

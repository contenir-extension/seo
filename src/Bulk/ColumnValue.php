<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function is_scalar;
use function is_string;
use function trim;

/**
 * A value a bulk fix reads and writes in a record's column: the column's own text, or one text under a key of the
 * JSON object a column such as a record's advanced overrides holds, leaving the object's other keys as they are.
 */
final class ColumnValue
{
    /**
     * The text a stored value holds, under $key when there is one; null when it holds nothing.
     */
    public static function read(mixed $stored, ?string $key): ?string
    {
        if (null === $key) {
            return is_scalar($stored) && '' !== trim((string) $stored) ? (string) $stored : null;
        }

        /** @var mixed $value */
        $value = (JsonObject::of($stored) ?? [])[$key] ?? null;

        return is_string($value) && '' !== trim($value) ? $value : null;
    }

    /**
     * What to store so that $text is the value, under $key when there is one: the text itself, or the object with
     * its key set or, for no text, removed. Null when the stored value is something other than an object, which a
     * write would lose.
     */
    public static function write(mixed $stored, ?string $key, ?string $text): ?string
    {
        if (null === $key) {
            return $text ?? '';
        }

        $object = JsonObject::of($stored);

        return null === $object ? null : JsonObject::encode(JsonObject::with($object, $key, $text));
    }
}

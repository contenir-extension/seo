<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function trim;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The JSON object a column holds, such as a record's advanced overrides, read and kept whole so that changing
 * one key leaves the others as they are.
 */
final class JsonObject
{
    /**
     * The object as stored, as text: nothing at all for an empty one.
     *
     * @param array<string, mixed> $object
     */
    public static function encode(array $object): string
    {
        return [] === $object ? '' : (string) json_encode($object, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The object a stored value holds: empty for nothing at all, null for anything else.
     *
     * @return array<string, mixed>|null
     */
    public static function of(mixed $stored): ?array
    {
        if (null === $stored || (is_string($stored) && '' === trim($stored))) {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = is_string($stored) ? json_decode($stored, associative: true) : null;

        /** @var array<string, mixed>|null */
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * The object with $key set to $text, or taken out for no text.
     *
     * @param array<string, mixed> $object
     *
     * @return array<string, mixed>
     */
    public static function with(array $object, string $key, ?string $text): array
    {
        if (null === $text) {
            unset($object[$key]);

            return $object;
        }

        $object[$key] = $text;

        return $object;
    }
}

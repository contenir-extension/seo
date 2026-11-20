<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Seo\Sections\SeoBlock;

use function is_scalar;
use function trim;

/**
 * Where a record's default for an SEO role comes from, and what it is: one entry of ControlDefaults.
 *
 * @phpstan-type Default array{value: ?string, from: string, column: ?string}
 */
final class RoleColumn
{
    /**
     * @return Default
     */
    public static function entry(?string $value, string $from, ?string $column = null): array
    {
        return ['value' => $value, 'from' => $from, 'column' => $column];
    }

    /**
     * The first of a role's columns that holds text in $record, with that text.
     *
     * @param array<string, mixed> $record
     *
     * @return array{string, string}|null
     */
    public static function first(SeoBlock $seo, string $role, array $record): ?array
    {
        foreach ($seo->roles[$role] ?? [] as $column) {
            /** @var mixed $value */
            $value = $record[$column] ?? null;
            $text  = is_scalar($value) ? trim((string) $value) : '';
            if ('' !== $text) {
                return [$column, $text];
            }
        }

        return null;
    }
}

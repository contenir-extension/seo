<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Extension\Api\Section\SectionBlock;

use function is_scalar;
use function trim;

/**
 * A record's value for an SEO role: the first of the role's columns that
 * holds text, and the field a note about the role belongs to, which is the
 * first of the role's columns the section has, or none for the whole record.
 */
final readonly class MetaValues
{
    /**
     * @param array<string, mixed> $values The record's values, as they are about to be written
     */
    public function __construct(
        private SectionBlock $block,
        private array $values,
    ) {}

    /**
     * @param list<string> $columns
     */
    public function field(array $columns): string
    {
        foreach ($columns as $column) {
            if ($this->block->hasField($column)) {
                return $column;
            }
        }

        return '';
    }

    /**
     * @param list<string> $columns
     */
    public function text(array $columns): ?string
    {
        foreach ($columns as $column) {
            /** @var mixed $value */
            $value = $this->values[$column] ?? null;
            $text  = is_scalar($value) ? trim((string) $value) : '';
            if ('' !== $text) {
                return $text;
            }
        }

        return null;
    }
}

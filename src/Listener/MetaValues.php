<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Section\DerivedContent;
use Contenir\Seo\Sections\Overrides;
use Contenir\Seo\Sections\SeoBlock;

use function in_array;
use function is_scalar;
use function trim;

/**
 * A record's value for an SEO role: what its advanced column holds for a title
 * or description, or else the first of the role's columns that holds text. A
 * note about the role belongs to the advanced field where the section has one
 * and the role can be kept there, or else the first of the role's columns the
 * section has, or none for the whole record.
 *
 * @mago-expect lint:cyclomatic-complexity Each role is read from the advanced column, the columns and the Section field.
 */
final readonly class MetaValues
{
    /** The roles a record keeps in its advanced column. */
    private const array IN_ADVANCED = ['title', 'description'];

    /**
     * @param array<string, mixed> $values The record's values, as they are about to be written
     */
    public function __construct(
        private SectionBlock $block,
        private array $values,
        private SeoBlock $seo,
    ) {}

    /**
     * The description the record's Section field gives where its columns give none, or null when it has none
     * or its columns already do.
     */
    public function derivedDescription(): ?string
    {
        return null === $this->text('description') ? (new DerivedContent($this->values))->description() : null;
    }

    public function field(string $role): string
    {
        $advanced = $this->advanced($role);
        $fields   = [...(null === $advanced ? [] : [$advanced]), ...($this->seo->roles[$role] ?? [])];

        foreach ($fields as $field) {
            if ($this->block->hasField($field)) {
                return $field;
            }
        }

        return '';
    }

    /**
     * Whether the record's Section field has an image to give where its columns give none.
     */
    public function hasDerivedImage(): bool
    {
        return null === $this->text('image') && (new DerivedContent($this->values))->hasImage();
    }

    public function text(string $role): ?string
    {
        $advanced = $this->advanced($role);
        $kept     = null === $advanced ? null : Overrides::from($this->values[$advanced] ?? null)[$role] ?? null;

        return $kept ?? $this->column($role);
    }

    private function advanced(string $role): ?string
    {
        return in_array($role, self::IN_ADVANCED, strict: true) ? $this->seo->advanced : null;
    }

    private function column(string $role): ?string
    {
        foreach ($this->seo->roles[$role] ?? [] as $column) {
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

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Section\SeoDeclaration;

use function array_map;
use function implode;
use function in_array;

/**
 * How one section's pages are described for search, as its `<seo>` block says it: the schema.org type, where
 * each role is read from in order, whether the page takes what the record's Section field gives where the
 * columns give nothing, and the section's own fields to read from.
 */
final readonly class SectionStrategy
{
    /** The roles whose sources a strategy sets. */
    public const array ROLES = ['title', 'description', 'image'];

    /**
     * @param array<string, list<string>> $sources Each role's columns, by role, with "image@alt" for the alt text
     * @param list<string>                $content The roles that may take what the Section field gives
     * @param list<string>                $fields  The section's fields
     *
     * @mago-expect lint:excessive-parameter-list One for each part of a strategy.
     */
    public function __construct(
        public string $section,
        public string $title,
        public string $schema,
        public array $sources,
        public bool $automatic,
        public array $content,
        public array $fields,
    ) {}

    public static function of(SectionBlock $block, string $title): self
    {
        $seo = SeoDeclaration::read($block->element);

        return new self(
            $block->section,
            $title,
            $seo->schema ?? '',
            $seo->roles,
            $seo->automatic,
            $seo->content,
            $block->fields,
        );
    }

    public function altSource(): string
    {
        return $this->sources['image@alt'][0] ?? '';
    }

    /**
     * What a role's sources read as on the Strategy screen.
     */
    public function sourcesLine(string $role): string
    {
        $parts = array_map(FieldLabel::of(...), $this->sourcesOf($role));
        if ($this->automatic && $this->takesContent($role)) {
            $parts[] = 'From content';
        }

        return [] === $parts ? 'None' : implode(', ', $parts);
    }

    /**
     * @return list<string>
     */
    public function sourcesOf(string $role): array
    {
        return $this->sources[$role] ?? [];
    }

    public function takesContent(string $role): bool
    {
        return in_array($role, $this->content, strict: true);
    }
}

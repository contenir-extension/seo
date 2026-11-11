<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Sections;

use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Override;

use function array_key_exists;
use function array_values;
use function count;

/**
 * Sections whose blocks an add-on may override, as a host keeps them: an override stands in for a declared
 * block, and its version counts the changes. It can be told to refuse every override, as a host that can't
 * keep one would, or every read of rows.
 */
final class OverridableSections implements SectionsInterface
{
    /**
     * @var array<string, Element> By section
     */
    public array $overrides = [];

    /**
     * @var list<int> The limit of each read of rows, in the order asked
     */
    public array $limits = [];

    private int $changes = 0;

    /**
     * @param array<string, SectionBlock> $declared By section
     */
    public function __construct(
        private readonly array $declared,
        private readonly ?BlockNotSavedException $refusal = null,
        private readonly ?RowsRefusedException $unread = null,
    ) {}

    #[Override]
    public function block(string $section): ?SectionBlock
    {
        $declared = $this->declared[$section] ?? null;
        $override = $this->overrides[$section] ?? null;

        return null === $declared || null === $override
            ? $declared
            : new SectionBlock($section, $override, $declared->fields, $declared->linkable);
    }

    #[Override]
    public function blocks(): array
    {
        $found = [];
        foreach ($this->declared as $section => $declared) {
            $found[] = $this->block($section) ?? $declared;
        }

        return array_values($found);
    }

    #[Override]
    public function declared(string $section): ?SectionBlock
    {
        return $this->declared[$section] ?? null;
    }

    #[Override]
    public function overridden(string $section): bool
    {
        return array_key_exists($section, $this->declared) && array_key_exists($section, $this->overrides);
    }

    #[Override]
    public function override(string $section, Element $block, string $version): void
    {
        if (null !== $this->refusal) {
            throw $this->refusal;
        }

        if ($version !== $this->overridesVersion()) {
            throw BlockNotSavedException::overridesChanged();
        }

        $this->overrides[$section] = $block;
        ++$this->changes;
    }

    #[Override]
    public function overridesVersion(): string
    {
        return 0 === $this->changes ? '' : (string) $this->changes;
    }

    #[Override]
    public function restore(string $section, string $version): void
    {
        if ($version !== $this->overridesVersion()) {
            throw BlockNotSavedException::overridesChanged();
        }

        unset($this->overrides[$section]);
        ++$this->changes;
    }

    #[Override]
    public function rows(string $section, array $columns, array $ids = [], int $limit = 100, int $offset = 0): array
    {
        $this->limits[] = $limit;
        if (null !== $this->unread) {
            throw $this->unread;
        }

        return [];
    }

    #[Override]
    public function save(string $section, Element $block, string $version): void
    {
        throw BlockNotSavedException::notAllowed();
    }

    #[Override]
    public function saves(): bool
    {
        return false;
    }

    #[Override]
    public function version(): string
    {
        return (string) count($this->declared);
    }
}

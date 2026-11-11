<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Override;

/**
 * The block saving and overriding a sections double of these tests leaves undone.
 */
trait SavesNoBlocksTrait
{
    #[Override]
    public function declared(string $section): ?SectionBlock
    {
        return $this->block($section);
    }

    #[Override]
    public function overridden(string $section): bool
    {
        return false;
    }

    #[Override]
    public function override(string $section, Element $block, string $version): void
    {
        throw BlockNotSavedException::refused('These sections keep no overrides');
    }

    #[Override]
    public function overridesVersion(): string
    {
        return '';
    }

    #[Override]
    public function restore(string $section, string $version): void {}

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
        return '';
    }
}

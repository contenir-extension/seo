<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Section\Element;
use Override;

/**
 * The block saving a sections double of these tests leaves undone.
 */
trait SavesNoBlocksTrait
{
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

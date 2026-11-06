<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Seo\Bulk\Summary;
use Contenir\Seo\Sections\ContentBlocks;

/**
 * What a record's Section field gives a page where its columns give none: a description as plain text a
 * search result could show, and whether it has an image.
 */
final readonly class DerivedContent
{
    private ContentBlocks $blocks;

    /**
     * @param array<string, mixed> $values The record's values
     */
    public function __construct(array $values)
    {
        $this->blocks = ContentBlocks::of($values[ContentBlocks::COLUMN] ?? null);
    }

    public function description(): ?string
    {
        $html = $this->blocks->description();

        return null === $html ? null : Summary::of($html);
    }

    public function hasImage(): bool
    {
        return [] !== $this->blocks->imageIds();
    }
}

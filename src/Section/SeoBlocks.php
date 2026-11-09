<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Strategy\StrategyOverrides;

use function array_map;

/**
 * The sections' `<seo>` blocks as this site describes them: as admin.xml says, except where the site has
 * changed a section's strategy on this environment, whose saved block takes the place of admin.xml's.
 */
final readonly class SeoBlocks
{
    public function __construct(
        private ExtensionContextInterface $context,
        private StrategyOverrides $overrides,
    ) {}

    public static function of(ExtensionContextInterface $context): self
    {
        return new self($context, StrategyOverrides::of($context));
    }

    /**
     * @return list<SectionBlock>
     */
    public function all(): array
    {
        return array_map($this->with(...), $this->context->sections()->blocks());
    }

    public function one(string $section): ?SectionBlock
    {
        $block = $this->context->sections()->block($section);

        return null === $block ? null : $this->with($block);
    }

    /**
     * The block as admin.xml gives it, with the site's own `<seo>` block in its place where it has one.
     */
    private function with(SectionBlock $block): SectionBlock
    {
        $saved = $this->overrides->for($block->section);

        return null === $saved ? $block : new SectionBlock($block->section, $saved, $block->fields, $block->linkable);
    }
}

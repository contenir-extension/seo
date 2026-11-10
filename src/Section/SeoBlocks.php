<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Strategy\SettingsStrategies;

/**
 * The sections' `<seo>` blocks as this site describes them: as admin.xml says, except where the site has
 * changed a section's strategy on this environment, which the host keeps and gives in the block's place.
 * Strategies saved before the host kept them are handed to it first.
 */
final readonly class SeoBlocks
{
    public function __construct(
        private ExtensionContextInterface $context,
        private SettingsStrategies $earlier,
    ) {}

    public static function of(ExtensionContextInterface $context): self
    {
        return new self($context, new SettingsStrategies($context));
    }

    /**
     * @return list<SectionBlock>
     */
    public function all(): array
    {
        $this->earlier->move();

        return $this->context->sections()->blocks();
    }

    public function one(string $section): ?SectionBlock
    {
        $this->earlier->move();

        return $this->context->sections()->block($section);
    }
}

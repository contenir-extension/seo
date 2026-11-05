<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;

/**
 * What the site last rendered for a record, while its columns haven't changed
 * since. It reads the kept answer and never asks the site, so a screen doesn't
 * wait on it.
 */
final readonly class CurrentInspection
{
    public function __construct(
        private ExtensionContextInterface $context,
    ) {}

    /**
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public function of(RecordReference $record, SectionBlock $block): ?Inspection
    {
        $kept = InspectionStore::at($this->context->sitePath())->load()[InspectionStore::key($record)] ?? null;
        if (null === $kept) {
            return null;
        }

        $columns = SeoSectionBlock::known($block, SeoDeclaration::read($block->element)->columns());
        $row     = $this->context->sections()->rows($block->section, $columns, [$record->id])[0] ?? null;

        return null !== $row && $kept->isCurrent($row) ? $kept : null;
    }
}

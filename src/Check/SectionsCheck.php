<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Seo\Sections\LinkedValue;
use Override;

use function array_diff;
use function array_keys;
use function array_merge;
use function count;
use function implode;
use function sprintf;

/**
 * Whether each section's `<seo>` block in admin.xml says what the site and
 * the save check need: a type, which the site's section map is keyed by; a
 * section with pages of its own, since a record without an address has
 * nothing to describe; and columns that are fields of the section, a linked
 * record's being the site's to find.
 */
final readonly class SectionsCheck implements CheckInterface
{
    /**
     * @return list<string>
     */
    private static function problems(SectionBlock $block): array
    {
        $seo      = SeoDeclaration::read($block->element);
        $named    = SeoDeclaration::named($block->element);
        $unknown  = array_diff($named, $block->fields, array_keys(LinkedValue::among($named)));
        $problems = [];
        if ('' === $seo->type) {
            $problems[] = sprintf('%s names no type for the site to describe it by', $block->section);
        }

        if (! $block->linkable) {
            $problems[] = sprintf('%s has no pages of its own to describe', $block->section);
        }

        if ([] !== $unknown) {
            $problems[] = sprintf('%s names %s, which it has no field for', $block->section, implode(', ', $unknown));
        }

        return $problems;
    }

    /**
     * What the site says when it won't lend the columns the block reads, so the section goes unreported.
     *
     * @return list<string>
     */
    private static function unread(ExtensionContextInterface $context, SectionBlock $block): array
    {
        try {
            $context->sections()->rows($block->section, SeoSectionBlock::reported($block), limit: 0);
        } catch (RowsRefusedException $refused) {
            return [sprintf(
                '%s can\'t be read, so its records are not reported: %s',
                $block->section,
                $refused->getMessage(),
            )];
        }

        return [];
    }

    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $blocks   = SeoBlocks::of($context)->all();
        $problems = [];
        foreach ($blocks as $block) {
            $problems[] = self::problems($block);
            $problems[] = self::unread($context, $block);
        }

        $problems = array_merge(...$problems);
        if ([] === $blocks) {
            return CheckResult::skipped('No section declares an <seo> block in admin.xml.');
        }

        return [] === $problems
            ? CheckResult::success(sprintf(
                '%d %s described for search.',
                count($blocks),
                1 === count($blocks) ? 'section is' : 'sections are',
            ))
            : CheckResult::warning(sprintf('Check the <seo> blocks in admin.xml: %s.', implode('; ', $problems)));
    }

    #[Override]
    public function label(): string
    {
        return 'Sections described for search';
    }
}

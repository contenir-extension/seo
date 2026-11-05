<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Panel;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\Panel;
use Contenir\Extension\Api\Panel\RecordPanelInterface;
use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Seo\Inspect\CurrentInspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaLengths;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\SeoSettings;
use Contenir\Seo\Sections\SeoBlock;
use Override;

use function array_key_exists;
use function array_map;
use function in_array;
use function mb_strlen;
use function mb_substr;
use function rawurlencode;
use function rtrim;
use function sprintf;

/**
 * "Search and social", beside a saved record of a section with an `<seo>`
 * block: its title and description as search results show them, with
 * their lengths, whether it is hidden from search, what to fix, and, once it is published, links to the page and to Google's test of
 * its structured data. It reads the record as stored, so it shows the last
 * save, and needs the site to bind it: `<panels><panel extension="seo"
 * name="search"/></panels>`.
 */
final readonly class SearchPanel implements RecordPanelInterface
{
    public const string NAME = 'search';

    /** The SEO area's Preview screen for a record, where the site is asked what it renders. */
    private const string PREVIEW = '/admin/extension/seo/preview?section=%s&id=%s';

    private const array OFF = ['0', 0, false, ''];

    /**
     * Whether the record's Discoverable switch, the first column of its block's `discoverable`
     * role (`visible` by convention), is off.
     *
     * @param array<string, mixed> $values
     */
    private static function hidden(SeoBlock $seo, array $values): bool
    {
        $column = ($seo->roles['discoverable'] ?? ['visible'])[0] ?? null;

        return (
            null !== $column
                && array_key_exists($column, $values)
                && in_array($values[$column], self::OFF, strict: true)
        );
    }

    private static function shown(string $text): string
    {
        return mb_strlen($text) > MetaLengths::DESCRIPTION_MAX
            ? rtrim(mb_substr($text, start: 0, length: MetaLengths::DESCRIPTION_MAX - 1)) . '…'
            : $text;
    }

    /**
     * @throws RowsRefusedException When the section's columns can't be read.
     * @throws UnknownSettingException When the host has no Search Appearance settings.
     */
    #[Override]
    public function panel(RecordView $record, ExtensionContextInterface $context): ?Panel
    {
        $block = $context->sections()->block($record->section);
        if (null === $block) {
            return null;
        }

        $seo         = SeoDeclaration::read($block->element);
        $values      = new MetaValues($block, $record->values);
        $template    = (new SeoSettings())->config($context)->title;
        $site        = (new CurrentInspection($context))->of($record->reference(), $block);
        $facts       = null === $site ? PanelFacts::ofColumns($seo, $values, $template) : PanelFacts::ofSite($site);
        $shown       = $facts->title;
        $description = $facts->description;
        $url         = $context->urls()->url($record->reference());
        $preview     = sprintf(
            self::PREVIEW,
            rawurlencode($record->section),
            rawurlencode((string) $record->reference()->id),
        );

        return new Panel(
            'Search and social',
            [
                null === $shown
                    ? 'Title: none yet.'
                    : sprintf(
                        'Title: %s (%d of about %d characters)',
                        $shown,
                        mb_strlen($shown),
                        MetaLengths::TITLE_MAX,
                    ),
                null === $description
                    ? 'Description: none yet.'
                    : sprintf(
                        'Description: %s (%d of about %d characters)',
                        self::shown($description),
                        mb_strlen($description),
                        MetaLengths::DESCRIPTION_MAX,
                    ),
                ...(self::hidden($seo, $record->values) ? ['Hidden from search: its Discoverable switch is off.'] : []),
                ...array_map(
                    static fn(MetaIssue $issue): string => "To fix: {$issue->message}",
                    $facts->issues,
                ),
            ],
            null === $url
                ? []
                : [
                    new Link('View the page', $url),
                    new Link('Preview in search', $preview),
                    new Link(
                        'Test its structured data',
                        'https://search.google.com/test/rich-results?url=' . rawurlencode($url),
                    ),
                ],
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Panel;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\Panel;
use Contenir\Extension\Api\Panel\PanelImage;
use Contenir\Extension\Api\Panel\PanelPreview;
use Contenir\Extension\Api\Panel\RecordPanelInterface;
use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Inspect\CurrentInspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Report\SearchReport;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\SeoSettings;
use Contenir\Seo\Sections\SeoBlock;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

use function array_key_exists;
use function implode;
use function in_array;
use function rawurlencode;
use function sprintf;

/**
 * "SEO", beside a saved record of a section with an `<seo>` block: its share image, how it reads as a
 * search result, then only what needs fixing, each a short row with a Fix button; and an Actions menu to preview
 * the record in search results and, once it has a page, to view it and to run Google's test of its
 * structured data. It reads the record as stored, so it shows the last save, and a site binds it with
 * `<panels><panel extension="seo" name="search"/></panels>`, or gets it by default on any section with an
 * `<seo>` block.
 */
final readonly class SearchPanel implements RecordPanelInterface
{
    public const string NAME = 'search';

    /** The SEO area's Preview screen for a record, where the site is asked what it renders. */
    private const string PREVIEW = '/admin/extension/seo/preview?section=%s&id=%s';

    private const array OFF = ['0', 0, false, ''];

    /**
     * The record's edit screen, which the Fix buttons go to, or null when the host can't say where it is.
     *
     * @throws ContainerExceptionInterface When the host's records can't be made.
     * @throws NotFoundExceptionInterface When the host has no records after saying it has.
     */
    private static function editLink(ExtensionContextInterface $context, RecordReference $record): ?Link
    {
        $services = $context->services();
        if (! $services->has(RecordsInterface::class)) {
            return null;
        }

        $url = $services->get(RecordsInterface::class)->describe($record)?->editUrl;

        return null === $url ? null : new Link('Fix', $url);
    }

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

    /**
     * @throws ContainerExceptionInterface When the host's services can't be reached.
     * @throws NotFoundExceptionInterface When the host's records can't be found.
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

        $seo      = SeoDeclaration::read($block->element);
        $values   = new MetaValues($block, $record->values, $seo);
        $template = (new SeoSettings())->config($context)->title;
        $site     = (new CurrentInspection($context))->of($record->reference(), $block);
        $facts    = null === $site ? PanelFacts::ofColumns($seo, $values, $template) : PanelFacts::ofSite($site);
        $issues   = $facts->issues;
        if (self::hidden($seo, $record->values)) {
            $issues[] = new MetaIssue('discoverable', 'discoverable', SearchReport::HIDDEN);
        }

        $rows = PanelRows::of($facts, $issues, self::editLink($context, $record->reference()));

        return new Panel(
            'SEO',
            paragraphs: [] === $facts->derived
                ? []
                : [sprintf('Taken from the content: %s.', implode(' and ', $facts->derived))],
            image: null === $facts->image ? null : new PanelImage($facts->image, $facts->title ?? ''),
            rows: $rows,
            menu: $this->menu($context, $record, OwnDescription::link($context, $record, $facts->derived)),
            preview: new PanelPreview($facts->title, $facts->description),
        );
    }

    /**
     * The panel's Actions menu: the record's preview in search results, $own when its content's description can be
     * saved as its own, and, once it has a page, the page and Google's test of its structured data.
     *
     * @return list<Link>
     */
    private function menu(ExtensionContextInterface $context, RecordView $record, ?Link $own): array
    {
        $preview = new Link('Preview in search', sprintf(
            self::PREVIEW,
            rawurlencode($record->section),
            rawurlencode((string) $record->reference()->id),
        ));
        $url   = $context->urls()->url($record->reference());
        $first = null === $own ? [$preview] : [$preview, $own];

        return (
            null === $url
                ? $first
                : [
                    ...$first,
                    new Link('View the page', $url),
                    new Link(
                        'Test its structured data',
                        'https://search.google.com/test/rich-results?url=' . rawurlencode($url),
                    ),
                ]
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Bulk\Summary;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\SeoConfig;

use function strip_tags;
use function trim;

/**
 * What a record's page uses for each of its SEO tab's fields when the record doesn't override it, and where that
 * comes from, for the tab to show beside its Override switches. Each default is
 * `['value' => ?string, 'from' => string, 'column' => ?string]`, where `from` is:
 *
 * - `column`: the record's own `column`, which the tab names by its label on the form;
 * - `content`: the record's Section field;
 * - `site`: the site's Search Appearance settings;
 * - `page`: the page itself (its own address);
 * - `section`: the section's `<seo>` block (its schema type);
 * - `none`: nothing, so search and sharing sites get nothing for it.
 *
 * The title is the record's own, before the site adds its name, as an override is.
 *
 * Read from the record as it was last saved.
 *
 * @phpstan-import-type Default from RoleColumn
 */
final class ControlDefaults
{
    /**
     * @param array<string, mixed> $record The record's values by column
     *
     * @return array{defaults: array<string, Default>, siteImage: ?array{url: string, alt: string}}
     */
    public static function of(SectionBlock $block, SeoBlock $seo, array $record, SeoConfig $site): array
    {
        $own = $record;
        if (null !== $seo->advanced) {
            unset($own[$seo->advanced]);
        }

        $values = new MetaValues($block, $own, $seo);
        $image  = ShareImageDefaults::image($seo, $own, $values, $site);

        return [
            'defaults'  => [
                'title'       => self::title($seo, $own),
                'description' => self::description($seo, $own, $values, $site),
                'image'       => $image,
                'image_alt'   => ShareImageDefaults::alt($seo, $own, $image, $site),
                'canonical'   => RoleColumn::entry(null, 'page'),
                'robots'      => RoleColumn::entry($site->robots->value, 'site'),
                'schema'      => RoleColumn::entry($seo->schema, 'section'),
            ],
            'siteImage' => null === $site->shareImage
                ? null
                : ['url' => $site->shareImage->url, 'alt' => $site->shareImage->alt],
        ];
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return Default
     */
    private static function description(SeoBlock $seo, array $record, MetaValues $values, SeoConfig $site): array
    {
        $column = RoleColumn::first($seo, 'description', $record);
        if (null !== $column) {
            $text = strip_tags($column[1]) === $column[1] ? $column[1] : Summary::cut($column[1]);

            return RoleColumn::entry($text, 'column', $column[0]);
        }

        $content = $values->shownDescription();
        if (null !== $content) {
            return RoleColumn::entry($content, 'content');
        }

        $default = trim($site->description);

        return '' === $default ? RoleColumn::entry(null, 'none') : RoleColumn::entry($default, 'site');
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return Default
     */
    private static function title(SeoBlock $seo, array $record): array
    {
        $column = RoleColumn::first($seo, 'title', $record);

        return null === $column ? RoleColumn::entry(null, 'none') : RoleColumn::entry($column[1], 'column', $column[0]);
    }
}

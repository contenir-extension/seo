<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\SeoConfig;

use function trim;

/**
 * The share image a record's page uses when the record doesn't override it, and its description: the section's
 * image column, else an image of its Section field, else the site's share image.
 *
 * @phpstan-import-type Default from RoleColumn
 */
final class ShareImageDefaults
{
    /**
     * @param array<string, mixed> $record
     * @param Default              $image  The image it describes
     *
     * @return Default
     */
    public static function alt(SeoBlock $seo, array $record, array $image, SeoConfig $site): array
    {
        if ('site' === $image['from']) {
            $alt = trim($site->shareImage->alt ?? '');

            return '' === $alt ? RoleColumn::entry(null, 'none') : RoleColumn::entry($alt, 'site');
        }

        $column = RoleColumn::first($seo, 'image@alt', $record);

        return null === $column ? RoleColumn::entry(null, 'none') : RoleColumn::entry($column[1], 'column', $column[0]);
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return Default
     */
    public static function image(SeoBlock $seo, array $record, MetaValues $values, SeoConfig $site): array
    {
        $column = RoleColumn::first($seo, 'image', $record);
        if (null !== $column) {
            return RoleColumn::entry($site->site->absolute($column[1]) ?? $column[1], 'column', $column[0]);
        }

        if ($values->hasDerivedImage()) {
            return RoleColumn::entry(null, 'content');
        }

        return null === $site->shareImage
            ? RoleColumn::entry(null, 'none')
            : RoleColumn::entry($site->shareImage->url, 'site');
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use function filter_var;
use function str_starts_with;

use const FILTER_VALIDATE_URL;

/**
 * What a record's share image override must be for the site to use it: the site's share image (`@site`), a path
 * on the site, or a full http(s) address.
 */
final class ShareImageRule
{
    public const string SITE = '@site';

    public static function problem(?string $image): ?string
    {
        return null === $image || self::SITE === $image || self::isPath($image) || self::isUrl($image)
            ? null
            : 'The share image must be an image on this site, such as /asset/library/house.jpg, or a full address.';
    }

    private static function isPath(string $image): bool
    {
        return str_starts_with($image, '/') && ! str_starts_with($image, '//');
    }

    private static function isUrl(string $image): bool
    {
        return (
            false !== filter_var($image, FILTER_VALIDATE_URL)
                && (str_starts_with($image, 'https://') || str_starts_with($image, 'http://'))
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Seo\Visibility\Visibility;

use function array_intersect;
use function array_map;
use function explode;
use function strrchr;
use function strtolower;
use function substr;
use function trim;

/**
 * Whether a robots value, as a meta tag or an X-Robots-Tag header holds it, keeps a page out of search
 * results: `noindex`, or `none`, which means the same. A rule addressed to one crawler
 * (`googlebot: noindex`) counts too.
 */
final class Noindex
{
    private const array KEEPS_OUT = ['noindex', 'none'];

    public static function in(?string $robots): bool
    {
        return (
            [] !== array_intersect(
                array_map(self::rule(...), explode(',', strtolower((string) $robots))),
                self::KEEPS_OUT,
            )
        );
    }

    /**
     * Whether a page itself kept search engines out, going by its robots meta tag and its X-Robots-Tag
     * header. What the whole site sends doesn't count: the header of a copy hidden from search, or a tag
     * that only repeats the site's own setting.
     *
     * @param string $site The robots value the site gives every page
     */
    public static function onPage(?string $tag, ?string $header, string $site = ''): bool
    {
        $sent = strtolower(trim((string) $header));

        return (self::in($sent) && Visibility::HIDDEN->value !== $sent) || (self::in($tag) && ! self::in($site));
    }

    private static function rule(string $part): string
    {
        $named = strrchr($part, needle: ':');

        return trim(false === $named ? $part : substr($named, offset: 1));
    }
}

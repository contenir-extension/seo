<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Seo\Inspect\SiteHead;
use Contenir\Extension\Seo\Listener\MetaValues;

use function json_encode;
use function rawurlencode;
use function sprintf;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * What the Preview screen shows: the site's head, or the record's own columns
 * without it.
 */
final class PreviewValues
{
    private const string RICH_RESULTS = 'https://search.google.com/test/rich-results?url=%s';

    /**
     * @return array<string, bool|string|null>
     */
    public static function of(?SiteHead $head, MetaValues $raw, ?string $url): array
    {
        return [
            'approximate' => null === $head,
            'url'         => $url,
            'rich'        => null === $url ? null : sprintf(self::RICH_RESULTS, rawurlencode($url)),
            ...(null === $head ? self::columns($raw) : self::site($head)),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function columns(MetaValues $raw): array
    {
        return [
            'title'       => $raw->text('title'),
            'description' => $raw->text('description'),
            'image'       => null,
            'imageAlt'    => null,
            'canonical'   => null,
            'robots'      => null,
            'jsonLd'      => null,
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function site(SiteHead $head): array
    {
        return [
            'title'       => $head->title,
            'description' => $head->description,
            'image'       => $head->image,
            'imageAlt'    => $head->imageAlt,
            'canonical'   => $head->canonical,
            'robots'      => $head->robots ?? $head->robotsHeader,
            'jsonLd'      => [] === $head->jsonLd
                ? null
                : (string) json_encode(
                    $head->jsonLd,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
                ),
        ];
    }
}

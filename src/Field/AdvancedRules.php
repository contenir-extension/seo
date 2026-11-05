<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use function array_diff;
use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function filter_var;
use function in_array;
use function str_starts_with;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_URL;

/**
 * What a record's SEO overrides must be for the site to use them.
 */
final class AdvancedRules
{
    /** @var list<string> */
    private const array ROBOTS = [
        'all',
        'follow',
        'index',
        'noarchive',
        'nofollow',
        'noimageindex',
        'noindex',
        'none',
        'nosnippet',
    ];

    /** @var list<string> */
    private const array SCHEMAS = [
        'WebPage',
        'AboutPage',
        'ContactPage',
        'CollectionPage',
        'CreativeWork',
        'Article',
        'NewsArticle',
        'BlogPosting',
    ];

    /**
     * @param array<string, string> $overrides
     *
     * @return list<string>
     */
    public static function problems(array $overrides): array
    {
        return array_values(array_filter([
            self::canonical($overrides['canonical'] ?? null),
            self::image($overrides['image'] ?? null),
            self::robots($overrides['robots'] ?? 'all'),
            self::schema($overrides['schema'] ?? null),
        ]));
    }

    private static function canonical(?string $canonical): ?string
    {
        return null === $canonical || self::isUrl($canonical)
            ? null
            : 'The canonical address must be a full address, such as https://www.example.com/about.';
    }

    private static function image(?string $image): ?string
    {
        return null === $image || self::isUrl($image) || str_starts_with($image, '/')
            ? null
            : 'The share image must be a full address, or a path on the site starting with /.';
    }

    private static function isUrl(string $value): bool
    {
        return (
            false !== filter_var($value, FILTER_VALIDATE_URL)
                && (str_starts_with($value, 'https://') || str_starts_with($value, 'http://'))
        );
    }

    private static function robots(string $robots): ?string
    {
        $rules = array_map(static fn(string $rule): string => strtolower(trim($rule)), explode(',', $robots));

        return [] === array_diff($rules, self::ROBOTS)
            ? null
            : 'The robots rule must be made of rules such as noindex and nofollow, separated by commas.';
    }

    private static function schema(?string $schema): ?string
    {
        return null === $schema || in_array($schema, self::SCHEMAS, strict: true)
            ? null
            : 'The schema type must be one of the types the site can describe.';
    }
}

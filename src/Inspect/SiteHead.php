<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use function array_filter;
use function array_values;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;

/**
 * What the site's page for a record puts in its head, as its /api/seo/inspect
 * endpoint reads it: what search engines and sharing sites would see.
 */
final readonly class SiteHead
{
    /**
     * @param list<array<array-key, mixed>> $jsonLd
     *
     * @mago-expect lint:excessive-parameter-list One value per thing the endpoint reads from the page's head.
     */
    public function __construct(
        public ?string $title,
        public ?string $description,
        public ?string $image,
        public ?string $imageAlt,
        public ?string $canonical,
        public ?string $robots,
        public ?string $robotsHeader,
        public array $jsonLd,
    ) {}

    /**
     * The head in the endpoint's answer, or null for an answer that doesn't hold
     * one, as when the page itself failed.
     */
    public static function fromJson(string $json): ?self
    {
        /** @var mixed $values */
        $values = json_decode($json, associative: true);
        if (! is_array($values) || ! is_int($values['status'] ?? null) || 200 !== $values['status']) {
            return null;
        }

        return new self(
            self::text($values, 'title'),
            self::text($values, 'description'),
            self::text($values, 'image'),
            self::text($values, 'imageAlt'),
            self::text($values, 'canonical'),
            self::text($values, 'robots'),
            self::text($values, 'robotsHeader'),
            self::nodes($values),
        );
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<array<array-key, mixed>>
     */
    private static function nodes(array $values): array
    {
        $nodes = is_array($values['jsonLd'] ?? null) ? $values['jsonLd'] : [];

        return array_values(array_filter($nodes, is_array(...)));
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $key): ?string
    {
        return is_string($values[$key] ?? null) && '' !== $values[$key] ? $values[$key] : null;
    }
}

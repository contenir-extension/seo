<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaLengths;

use function array_filter;
use function array_values;

/**
 * What a record needs, going by what the site rendered for it, where the title
 * already has the site's name in it.
 */
final class InspectionIssues
{
    /**
     * @return list<MetaIssue>
     */
    public static function of(Inspection $inspection): array
    {
        return array_values(array_filter([
            self::title($inspection->title),
            self::description($inspection->description),
            null === $inspection->image
                ? new MetaIssue('image', '', 'Add an image for shared links.', missing: true)
                : null,
        ]));
    }

    private static function description(?string $description): ?MetaIssue
    {
        if (null === $description) {
            return new MetaIssue(
                'description',
                '',
                'Add a description for search results and shared links.',
                missing: true,
            );
        }

        $message = MetaLengths::description($description);

        return null === $message ? null : new MetaIssue('description', '', $message);
    }

    private static function title(?string $title): ?MetaIssue
    {
        if (null === $title) {
            return new MetaIssue('title', '', 'Add a title for search results.', missing: true);
        }

        $message = MetaLengths::title($title);

        return null === $message ? null : new MetaIssue('title', '', $message);
    }
}

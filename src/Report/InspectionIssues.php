<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaLengths;

use function array_filter;
use function array_values;
use function sprintf;

/**
 * What a record needs, going by what the site rendered for it, where the title
 * already has the site's name in it.
 */
final class InspectionIssues
{
    /** The size sharing sites show an image at. */
    public const int IMAGE_WIDTH = 1200;

    public const int IMAGE_HEIGHT = 630;

    /**
     * @return list<MetaIssue>
     */
    public static function of(Inspection $inspection): array
    {
        return array_values(array_filter([
            self::title($inspection->title),
            self::description($inspection->description),
            self::image($inspection),
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

    private static function image(Inspection $inspection): ?MetaIssue
    {
        return null === $inspection->image
            ? new MetaIssue('image', '', 'Add an image for shared links.', missing: true)
            : self::small($inspection->imageWidth, $inspection->imageHeight);
    }

    private static function small(?int $width, ?int $height): ?MetaIssue
    {
        return null === $width || null === $height || ($width >= self::IMAGE_WIDTH && $height >= self::IMAGE_HEIGHT)
            ? null
            : new MetaIssue('image', '', sprintf(
                'The share image is %d×%d, and sharing sites show about %d×%d, so it may look soft.',
                $width,
                $height,
                self::IMAGE_WIDTH,
                self::IMAGE_HEIGHT,
            ));
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

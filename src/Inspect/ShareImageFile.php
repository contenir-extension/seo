<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use function getimagesize;
use function is_file;
use function is_string;
use function parse_url;
use function preg_replace;
use function rawurldecode;
use function str_contains;

use const PHP_URL_PATH;

/**
 * The size of the file a page's share image was made from: a share variant says
 * nothing of it, as it always comes out the same size. It's found under the site's
 * public folder, so an image held elsewhere has no size to give.
 */
final readonly class ShareImageFile
{
    private const string VARIANT = '~/_variant/[^/]+/~';

    public function __construct(
        private string $sitePath,
    ) {}

    /**
     * @return array{int, int}|null Its width and height, or null when it can't be read
     */
    public function size(?string $url): ?array
    {
        $path = null === $url ? null : parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || str_contains($path, '..')) {
            return null;
        }

        $file =
            $this->sitePath
            . '/public'
            . rawurldecode((string) preg_replace(self::VARIANT, replacement: '/', subject: $path));
        $size = is_file($file) ? getimagesize($file) : false;

        return false === $size ? null : [$size[0], $size[1]];
    }
}

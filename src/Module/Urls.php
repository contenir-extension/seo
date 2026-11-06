<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use function http_build_query;

/**
 * The address of one of the module's own screens, for a handler to send the person on to. The host gives a
 * handler no way to ask for it, so this is the CMS's own route, as SettingsLinks is for the settings.
 */
final class Urls
{
    private const string PATH = '/admin/extension/seo/';

    /**
     * @param array<string, scalar> $query
     */
    public static function to(string $handler, array $query = []): string
    {
        return self::PATH . $handler . ([] === $query ? '' : '?' . http_build_query($query));
    }
}

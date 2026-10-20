<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo;

use function array_replace_recursive;
use function is_array;
use function is_file;

/**
 * The site's own settings, read from its settings files as the public site
 * reads them: config/autoload/settings.global.php, with settings.local.php
 * over it. The API has no way to read the CMS's core settings, such as the
 * site URL, so the add-on reads the files the CMS writes.
 *
 * @api
 */
final readonly class SiteSettingsFiles
{
    public const string DIRECTORY = 'config/autoload';

    /**
     * @return array<array-key, mixed>
     *
     * @mago-expect analysis:mixed-assignment A settings file returns whatever it returns; it is checked here.
     */
    private static function settings(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        $config   = include $path;
        $settings = is_array($config) ? $config['settings'] ?? null : null;

        return is_array($settings) ? $settings : [];
    }

    /**
     * The settings under "settings" in both files, local over global.
     *
     * @return array<array-key, mixed>
     */
    public function read(string $sitePath): array
    {
        return array_replace_recursive(
            self::settings("{$sitePath}/" . self::DIRECTORY . '/settings.global.php'),
            self::settings("{$sitePath}/" . self::DIRECTORY . '/settings.local.php'),
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo;

use function array_key_exists;
use function array_replace_recursive;
use function is_array;
use function is_file;

/**
 * The site's own settings, read from its settings files as the public site
 * reads them: config/autoload/settings.global.php, with settings.local.php
 * over it. A Mezzio site keeps the shared file's settings at its top level,
 * where its application config merges them, and the local file's under
 * "settings"; the shared file is read under "settings" when it has that key,
 * as the CMS reads it. The API has no way to read the CMS's core settings, such as the
 * site URL, so the add-on reads the files the CMS writes.
 *
 * @api
 */
final readonly class SiteSettingsFiles
{
    public const string DIRECTORY = 'config/autoload';

    /**
     * What a settings file returns, or nothing when the site has no such file.
     */
    private static function file(string $path): mixed
    {
        return is_file($path) ? (include $path) : null;
    }

    /**
     * The local file's settings: those under "settings".
     *
     * @return array<array-key, mixed>
     */
    private static function local(mixed $config): array
    {
        /** @var mixed $settings */
        $settings = is_array($config) ? $config['settings'] ?? null : null;

        return is_array($settings) ? $settings : [];
    }

    /**
     * The shared file's settings: those under "settings" when it has that key, otherwise the whole file.
     *
     * @return array<array-key, mixed>
     */
    private static function shared(mixed $config): array
    {
        return is_array($config) && ! array_key_exists('settings', $config) ? $config : self::local($config);
    }

    /**
     * The settings of both files, local over shared.
     *
     * @return array<array-key, mixed>
     */
    public function read(string $sitePath): array
    {
        return array_replace_recursive(
            self::shared(self::file("{$sitePath}/" . self::DIRECTORY . '/settings.global.php')),
            self::local(self::file("{$sitePath}/" . self::DIRECTORY . '/settings.local.php')),
        );
    }
}

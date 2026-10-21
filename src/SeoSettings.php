<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Section\SitemapSection;
use Contenir\Extension\Seo\Section\VerificationSection;
use Contenir\Seo\SeoConfig;

use function array_key_exists;
use function array_replace_recursive;
use function explode;

/**
 * The site's SEO as contenir/contenir-seo reads it: the site's own settings files,
 * with the add-on's three sections, as the CMS saves them, over them.
 *
 * @api
 */
final readonly class SeoSettings
{
    public function __construct(
        private SiteSettingsFiles $files = new SiteSettingsFiles(),
    ) {}

    /**
     * $value under a dotted key, as nested arrays.
     *
     * @return array<string, mixed>
     */
    private static function nest(string $key, mixed $value): array
    {
        $segments = explode('.', $key, limit: 2);

        return [$segments[0] => array_key_exists(1, $segments) ? self::nest($segments[1], $value) : $value];
    }

    /**
     * @throws UnknownSettingException Never: every section and field read is declared.
     */
    public function config(ExtensionContextInterface $context): SeoConfig
    {
        $settings = $this->files->read($context->sitePath());
        $sections = [
            AppearanceSection::NAME   => AppearanceSection::KEYS,
            VerificationSection::NAME => VerificationSection::keys(),
            SitemapSection::NAME      => SitemapSection::KEYS,
        ];
        foreach ($sections as $section => $keys) {
            $values = $context->settings($section);
            foreach ($keys as $field => $key) {
                $settings = array_replace_recursive($settings, self::nest($key, $values->get((string) $field)));
            }
        }

        return SeoConfig::fromSettings($settings);
    }
}

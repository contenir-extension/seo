<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Section\SitemapSection;
use Contenir\Extension\Seo\Section\VerificationSection;

/**
 * Where the add-on's settings sections are in the CMS. The extension API has no
 * way to ask for a settings screen's address, so this is the CMS's own route, as
 * the panel's link to the Preview screen is.
 */
final class SettingsLinks
{
    private const string PATH = '/admin/settings/common/view/category/seo/section/';

    /**
     * @return array<string, string> The section's title, by the address of its screen
     */
    public static function all(): array
    {
        return [
            self::PATH . AppearanceSection::NAME   => 'Search Appearance',
            self::PATH . VerificationSection::NAME => 'Site Verification',
            self::PATH . SitemapSection::NAME      => 'Sitemap',
        ];
    }

    /**
     * Where a site-wide issue is settled: the screen of the setting it is about, or null for a kind of issue
     * that is about a record.
     */
    public static function of(IssueKind $kind): ?string
    {
        return match ($kind) {
            IssueKind::DefaultsNotSet => self::PATH . AppearanceSection::NAME,
            IssueKind::SitemapOff     => self::PATH . SitemapSection::NAME,
            default                   => null,
        };
    }
}

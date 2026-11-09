<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Section\SitemapSection;
use Contenir\Extension\Seo\Section\VerificationSection;

use function array_key_exists;
use function str_replace;

/**
 * Where the add-on's settings sections are in the CMS. The extension API has no
 * way to ask for a settings screen's address, so this is the CMS's own route, as
 * the panel's link to the Preview screen is.
 */
final class SettingsLinks
{
    private const string PATH = '/admin/settings/common/view/category/seo/section/';

    /** The settings screens the SEO menu leads to, by the name its link gives. */
    public const array SCREENS = [
        'appearance'   => AppearanceSection::NAME,
        'verification' => VerificationSection::NAME,
        'sitemap'      => SitemapSection::NAME,
    ];

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
     * The edit form of one of the settings screens, or null for a name that is none of them.
     */
    public static function edit(string $screen): ?string
    {
        return array_key_exists($screen, self::SCREENS) ? self::editing(self::PATH . self::SCREENS[$screen]) : null;
    }

    /**
     * The same screen as its edit form, where a fix is made, rather than the read-only view.
     */
    public static function editing(string $address): string
    {
        return str_replace('/common/view/', replace: '/common/update/', subject: $address);
    }

    /**
     * Where a site-wide issue is settled: the form of the setting it is about, ready to edit, or null for a kind of
     * issue that is about a record.
     */
    public static function of(IssueKind $kind): ?string
    {
        return match ($kind) {
            IssueKind::DefaultsNotSet => self::editing(self::PATH . AppearanceSection::NAME),
            IssueKind::SitemapOff     => self::editing(self::PATH . SitemapSection::NAME),
            default                   => null,
        };
    }
}

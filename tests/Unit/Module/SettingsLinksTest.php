<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Module\SettingsLinks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SettingsLinksTest extends TestCase
{
    /**
     * @return array<string, array{IssueKind}>
     */
    public static function recordIssues(): array
    {
        return [
            'a missing title'     => [IssueKind::MissingTitle],
            'a hidden record'     => [IssueKind::Hidden],
            'a short description' => [IssueKind::DescriptionLength],
        ];
    }

    #[Test]
    public function listsTheThreeScreensByTheirAddresses(): void
    {
        static::assertSame(
            [
                '/admin/settings/common/view/category/seo/section/seo'              => 'Search Appearance',
                '/admin/settings/common/view/category/seo/section/seo-verification' => 'Site Verification',
                '/admin/settings/common/view/category/seo/section/seo-sitemap'      => 'Sitemap',
            ],
            SettingsLinks::all(),
        );
    }

    #[Test]
    public function sendsASitemapIssueToTheSitemapScreen(): void
    {
        static::assertSame(
            '/admin/settings/common/update/category/seo/section/seo-sitemap',
            SettingsLinks::of(IssueKind::SitemapOff),
        );
    }

    #[Test]
    public function sendsASitewideDefaultsIssueToTheSearchAppearanceScreen(): void
    {
        static::assertSame(
            '/admin/settings/common/update/category/seo/section/seo',
            SettingsLinks::of(IssueKind::DefaultsNotSet),
        );
    }

    #[Test]
    #[DataProvider('recordIssues')]
    public function sendsNoRecordIssueToASettingsScreen(IssueKind $kind): void
    {
        static::assertNull(SettingsLinks::of($kind));
    }

    #[Test]
    public function turnsAViewAddressIntoItsEditForm(): void
    {
        static::assertSame(
            '/admin/settings/common/update/category/seo/section/seo',
            SettingsLinks::editing('/admin/settings/common/view/category/seo/section/seo'),
        );
    }
}

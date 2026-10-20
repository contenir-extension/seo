<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo;

use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Seo\Check\SiteUrlCheck;
use Contenir\Extension\Seo\Check\VisibilityCheck;
use Contenir\Extension\Seo\Field\BingTokenField;
use Contenir\Extension\Seo\Field\GoogleTokenField;
use Contenir\Extension\Seo\Field\MetaTokenField;
use Contenir\Extension\Seo\Field\PinterestTokenField;
use Contenir\Extension\Seo\Field\RobotsRulesField;
use Contenir\Extension\Seo\Field\WorkflowsField;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Section\SitemapSection;
use Contenir\Extension\Seo\Section\VerificationSection;
use Override;

/**
 * The SEO add-on: three settings sections (Search Appearance, Site
 * Verification and Sitemap), field types that check verification tokens,
 * workflow names and robots.txt rules as they are saved, and two checks.
 * Public sites read the settings through contenir/seo's adapters.
 *
 * @api
 */
final class SeoExtension implements ExtensionInterface
{
    #[Override]
    public function register(Registry $registry): void
    {
        $registry->fieldType(GoogleTokenField::NAME, GoogleTokenField::class);
        $registry->fieldType(BingTokenField::NAME, BingTokenField::class);
        $registry->fieldType(PinterestTokenField::NAME, PinterestTokenField::class);
        $registry->fieldType(MetaTokenField::NAME, MetaTokenField::class);
        $registry->fieldType(WorkflowsField::NAME, WorkflowsField::class);
        $registry->fieldType(RobotsRulesField::NAME, RobotsRulesField::class);

        $registry->settingsSection(AppearanceSection::define());
        $registry->settingsSection(VerificationSection::define($registry->handle()));
        $registry->settingsSection(SitemapSection::define($registry->handle()));

        $registry->diagnostic('visibility', VisibilityCheck::class);
        $registry->diagnostic('site-url', SiteUrlCheck::class);
    }
}

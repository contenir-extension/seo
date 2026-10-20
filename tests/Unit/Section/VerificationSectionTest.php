<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Seo\Section\VerificationSection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class VerificationSectionTest extends TestCase
{
    #[Test]
    public function checksAndDescribesATokenForEachService(): void
    {
        static::assertSame(
            [
                [
                    'google_site_verification',
                    'Google Search Console',
                    'seo:google-verification',
                    'google.site_verification',
                    'The content value of the google-site-verification meta tag, or the whole tag. Search Console:'
                        . ' Settings, Ownership verification, HTML tag. Leave blank if the site isn\'t verified there.',
                ],
                [
                    'bing_site_verification',
                    'Bing Webmaster Tools',
                    'seo:bing-verification',
                    'extensions.seo.verify_bing',
                    'The content value of the msvalidate.01 meta tag, or the whole tag. Bing Webmaster Tools: add the'
                        . ' site and choose HTML Meta Tag. Leave blank if the site isn\'t verified there.',
                ],
                [
                    'pinterest_site_verification',
                    'Pinterest',
                    'seo:pinterest-verification',
                    'extensions.seo.verify_pinterest',
                    'The content value of the p:domain_verify meta tag, or the whole tag. Pinterest: Settings, Claimed'
                        . ' accounts, Websites, Claim, then Add HTML tag. Leave blank if the site isn\'t verified there.',
                ],
                [
                    'meta_site_verification',
                    'Meta (Facebook and Instagram)',
                    'seo:meta-verification',
                    'extensions.seo.verify_meta',
                    'The content value of the facebook-domain-verification meta tag, or the whole tag. Meta Business'
                        . ' Suite: Brand safety, Domains, then Meta-tag verification. Leave blank if the site isn\'t'
                        . ' verified there.',
                ],
            ],
            array_map(
                static fn(Field $field): array => [
                    $field->name,
                    $field->label,
                    $field->type,
                    $field->setting,
                    $field->description,
                ],
                VerificationSection::define(new Handle('seo'))->form->fields,
            ),
        );
    }

    #[Test]
    public function isSiteVerificationUnderSeo(): void
    {
        $section = VerificationSection::define(new Handle('seo'));

        static::assertSame(
            ['seo-verification', 'Site Verification', SettingsCategory::Seo, [], []],
            [$section->name, $section->title, $section->category, $section->form->groups, $section->contributions],
        );
    }

    #[Test]
    public function listsTheKeyOfEachField(): void
    {
        static::assertSame(
            [
                'google_site_verification'    => 'google.site_verification',
                'bing_site_verification'      => 'extensions.seo.verify_bing',
                'pinterest_site_verification' => 'extensions.seo.verify_pinterest',
                'meta_site_verification'      => 'extensions.seo.verify_meta',
            ],
            VerificationSection::keys(),
        );
    }
}

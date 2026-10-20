<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Extension\Seo\Field\BingTokenField;
use Contenir\Extension\Seo\Field\GoogleTokenField;
use Contenir\Extension\Seo\Field\MetaTokenField;
use Contenir\Extension\Seo\Field\PinterestTokenField;
use Contenir\Seo\Verification\Verifier;

use function sprintf;

/**
 * Site Verification under SEO, in place of the CMS's own section: a token
 * for each service that confirms who owns the site. Google's token keeps
 * the key the CMS used.
 *
 * @api
 */
final class VerificationSection
{
    public const string NAME = 'seo-verification';

    public const string GOOGLE = 'google_site_verification';

    public const string BING = 'bing_site_verification';

    public const string PINTEREST = 'pinterest_site_verification';

    public const string META = 'meta_site_verification';

    /**
     * Each field's service and field type.
     */
    public const array FIELDS = [
        self::GOOGLE    => [Verifier::Google, GoogleTokenField::NAME],
        self::BING      => [Verifier::Bing, BingTokenField::NAME],
        self::PINTEREST => [Verifier::Pinterest, PinterestTokenField::NAME],
        self::META      => [Verifier::Meta, MetaTokenField::NAME],
    ];

    /**
     * The section, with field types named for the add-on's handle.
     */
    public static function define(Handle $handle): SettingsSection
    {
        $fields = [];
        foreach (self::FIELDS as $name => [$verifier, $type]) {
            $fields[] = new Field(
                name: (string) $name,
                label: $verifier->label(),
                type: $handle->fieldType($type),
                description: sprintf(
                    'The content value of the %s meta tag, or the whole tag. %s Leave blank if the site isn\'t'
                        . ' verified there.',
                    $verifier->metaName(),
                    $verifier->hint(),
                ),
                setting: $verifier->settingKey(),
            );
        }

        return new SettingsSection(
            name: self::NAME,
            title: 'Site Verification',
            category: SettingsCategory::Seo,
            form: new Form($fields),
        );
    }

    /**
     * The settings key each field stores its value under.
     *
     * @return array<string, string>
     */
    public static function keys(): array
    {
        $keys = [];
        foreach (self::FIELDS as $name => [$verifier]) {
            $keys[(string) $name] = $verifier->settingKey();
        }

        return $keys;
    }
}

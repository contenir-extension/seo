<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Seo\Head\ShareImage;
use Contenir\Seo\Organisation\OrganisationType;
use Contenir\Seo\Robots\RobotsDirective;
use Contenir\Seo\SettingKeys;
use Contenir\Seo\Title\TitlePosition;
use Contenir\Seo\Title\TitleTemplate;
use Contenir\Seo\Visibility\Visibility;

use function sprintf;

/**
 * Search Appearance under SEO: how titles are built, what pages without
 * their own description or share image fall back to, who owns the site,
 * and whether search engines see this copy of it.
 *
 * @api
 */
final class AppearanceSection
{
    public const string NAME = 'seo';

    public const string SITE_NAME = 'site_name';

    public const string TITLE_SEPARATOR = 'title_separator';

    public const string TITLE_POSITION = 'title_position';

    public const string HOME_TITLE = 'home_title';

    public const string DESCRIPTION = 'description';

    public const string SHARE_IMAGE = 'share_image';

    public const string SHARE_IMAGE_ALT = 'share_image_alt';

    public const string ROBOTS = 'robots';

    public const string ORGANISATION_NAME = 'organisation_name';

    public const string ORGANISATION_TYPE = 'organisation_type';

    public const string ORGANISATION_LOGO = 'organisation_logo';

    public const string VISIBILITY = 'visibility';

    /**
     * Where uploaded images go, under the site's document root.
     */
    public const string IMAGE_PATH = '/asset/img/seo';

    /**
     * The settings key each field stores its value under.
     */
    public const array KEYS = [
        self::SITE_NAME         => SettingKeys::SITE_NAME,
        self::TITLE_SEPARATOR   => SettingKeys::TITLE_SEPARATOR,
        self::TITLE_POSITION    => SettingKeys::TITLE_POSITION,
        self::HOME_TITLE        => SettingKeys::HOME_TITLE,
        self::DESCRIPTION       => SettingKeys::DESCRIPTION,
        self::SHARE_IMAGE       => SettingKeys::SHARE_IMAGE,
        self::SHARE_IMAGE_ALT   => SettingKeys::SHARE_IMAGE_ALT,
        self::ROBOTS            => SettingKeys::ROBOTS,
        self::ORGANISATION_NAME => SettingKeys::ORGANISATION_NAME,
        self::ORGANISATION_TYPE => SettingKeys::ORGANISATION_TYPE,
        self::ORGANISATION_LOGO => SettingKeys::ORGANISATION_LOGO,
        self::VISIBILITY        => SettingKeys::VISIBILITY,
    ];

    public static function define(): SettingsSection
    {
        return new SettingsSection(
            name: self::NAME,
            title: 'Search Appearance',
            category: SettingsCategory::Seo,
            form: new Form(
                [
                    new Field(
                        name: self::SITE_NAME,
                        label: 'Site name',
                        description: 'The name page titles and share previews give the site. Leave blank to use'
                            . ' the name under Site.',
                        setting: SettingKeys::SITE_NAME,
                    ),
                    new Field(
                        name: self::TITLE_SEPARATOR,
                        label: 'Separator',
                        type: FieldType::Select,
                        description: 'Between the page title and the site name.',
                        setting: SettingKeys::TITLE_SEPARATOR,
                        default: TitleTemplate::SEPARATOR,
                        options: TitleTemplate::SEPARATORS,
                    ),
                    new Field(
                        name: self::TITLE_POSITION,
                        label: 'Site name in titles',
                        type: FieldType::Select,
                        description: 'Where the site name goes in each page\'s title: "About us | Example".',
                        setting: SettingKeys::TITLE_POSITION,
                        default: TitlePosition::After->value,
                        options: TitlePosition::options(),
                    ),
                    new Field(
                        name: self::HOME_TITLE,
                        label: 'Home page title',
                        description: 'The home page\'s whole title, used as it is. Leave blank to build it like'
                            . ' any other page\'s.',
                        setting: SettingKeys::HOME_TITLE,
                    ),
                    new Field(
                        name: self::DESCRIPTION,
                        label: 'Default description',
                        type: FieldType::Textarea,
                        description: 'For search results and share previews of pages without a description of'
                            . ' their own. Search engines show about 160 characters.',
                        setting: SettingKeys::DESCRIPTION,
                        attributes: ['rows' => 3, 'maxlength' => 300],
                    ),
                    new Field(
                        name: self::SHARE_IMAGE,
                        label: 'Default share image',
                        type: FieldType::Fileimage,
                        description: sprintf(
                            'Shown when a page without an image of its own is shared. %d × %d pixels works best.',
                            ShareImage::WIDTH,
                            ShareImage::HEIGHT,
                        ),
                        setting: SettingKeys::SHARE_IMAGE,
                        attributes: ['path' => self::IMAGE_PATH],
                    ),
                    new Field(
                        name: self::SHARE_IMAGE_ALT,
                        label: 'Share image description',
                        description: 'What the share image shows, for people who can\'t see it.',
                        setting: SettingKeys::SHARE_IMAGE_ALT,
                    ),
                    new Field(
                        name: self::ROBOTS,
                        label: 'Search engines may',
                        type: FieldType::Select,
                        description: 'What search engines may do with the site\'s pages, unless a page says'
                            . ' otherwise.',
                        setting: SettingKeys::ROBOTS,
                        default: RobotsDirective::IndexFollow->value,
                        options: RobotsDirective::options(),
                    ),
                    new Field(
                        name: self::ORGANISATION_NAME,
                        label: 'Name',
                        description: 'Who owns the site, as search engines describe it. Leave blank to use the'
                            . ' site name.',
                        setting: SettingKeys::ORGANISATION_NAME,
                    ),
                    new Field(
                        name: self::ORGANISATION_TYPE,
                        label: 'Type',
                        type: FieldType::Select,
                        description: 'A local business has premises that customers visit.',
                        setting: SettingKeys::ORGANISATION_TYPE,
                        default: OrganisationType::Organization->value,
                        options: OrganisationType::options(),
                    ),
                    new Field(
                        name: self::ORGANISATION_LOGO,
                        label: 'Logo',
                        type: FieldType::Fileimage,
                        description: 'A square logo of at least 112 × 112 pixels, on a plain background.',
                        setting: SettingKeys::ORGANISATION_LOGO,
                        attributes: ['path' => self::IMAGE_PATH],
                    ),
                    new Field(
                        name: self::VISIBILITY,
                        label: 'Search engines',
                        type: FieldType::Select,
                        description: 'Saved for this environment only. Automatic hides every copy of the site'
                            . ' that isn\'t at the site URL, such as staging, so it never appears in search'
                            . ' results.',
                        setting: SettingKeys::VISIBILITY,
                        default: Visibility::Automatic->value,
                        options: Visibility::options(),
                        local: true,
                    ),
                ],
                [
                    new Group('titles', 'Titles', [
                        self::SITE_NAME,
                        [self::TITLE_SEPARATOR, self::TITLE_POSITION],
                        self::HOME_TITLE,
                    ]),
                    new Group('defaults', 'Defaults', [
                        self::DESCRIPTION,
                        self::SHARE_IMAGE,
                        self::SHARE_IMAGE_ALT,
                        self::ROBOTS,
                    ]),
                    new Group(
                        'organisation',
                        'Organisation',
                        [
                            self::ORGANISATION_NAME,
                            self::ORGANISATION_TYPE,
                            self::ORGANISATION_LOGO,
                        ],
                    ),
                    new Group('environment', 'This environment', [self::VISIBILITY]),
                ],
            ),
        );
    }
}

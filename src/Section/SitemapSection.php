<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;
use Contenir\Extension\Seo\Field\RobotsRulesField;
use Contenir\Extension\Seo\Field\WorkflowsField;
use Contenir\Seo\SettingKeys;

/**
 * Sitemap under SEO, in place of the CMS's own section: the switch, the
 * workflows left out, and extra robots.txt rules, with a link to the
 * sitemap itself. The switch keeps the key the CMS used.
 *
 * @api
 */
final class SitemapSection
{
    public const string NAME = 'seo-sitemap';

    public const string ENABLED = 'sitemap';

    public const string EXCLUDE = 'sitemap_exclude';

    public const string ROBOTS_RULES = 'robots_rules';

    /**
     * The settings key each field stores its value under.
     */
    public const array KEYS = [
        self::ENABLED      => SettingKeys::SITEMAP_ENABLED,
        self::EXCLUDE      => SettingKeys::SITEMAP_EXCLUDE,
        self::ROBOTS_RULES => SettingKeys::ROBOTS_RULES,
    ];

    /**
     * The section, with field types named for the add-on's handle.
     */
    public static function define(Handle $handle): SettingsSection
    {
        return new SettingsSection(
            name: self::NAME,
            title: 'Sitemap',
            category: SettingsCategory::Seo,
            form: new Form(
                [
                    new Field(
                        name: self::ENABLED,
                        label: 'Publish a sitemap',
                        type: FieldType::Checkbox,
                        description: 'At /sitemap.xml, named in robots.txt so search engines find every page,'
                            . ' including those hidden from the menu.',
                        setting: SettingKeys::SITEMAP_ENABLED,
                        default: true,
                    ),
                    new Field(
                        name: self::EXCLUDE,
                        label: 'Leave out these workflows',
                        type: $handle->fieldType(WorkflowsField::NAME),
                        description: 'Kinds of page that shouldn\'t be listed, such as redirect or faq,'
                            . ' separated by commas. Pages search engines may not index are always left out.',
                        setting: SettingKeys::SITEMAP_EXCLUDE,
                    ),
                    new Field(
                        name: self::ROBOTS_RULES,
                        label: 'Extra robots.txt rules',
                        type: $handle->fieldType(RobotsRulesField::NAME),
                        description: 'Added after "Allow: /", one per line, such as "Disallow: /search", or'
                            . ' "User-agent: GPTBot" followed by "Disallow: /" to keep one crawler out.'
                            . ' A copy hidden from search engines ignores them.',
                        setting: SettingKeys::ROBOTS_RULES,
                        attributes: ['rows' => 5],
                    ),
                ],
                [
                    new Group('sitemap', 'Sitemap', [self::ENABLED, self::EXCLUDE]),
                    new Group('robots', 'robots.txt', [self::ROBOTS_RULES]),
                ],
            ),
            contributions: [
                new Contribution(
                    region: 'layout-nav',
                    type: 'link',
                    privilege: 'view',
                    label: 'View XML sitemap',
                    url: '/sitemap.xml',
                ),
            ],
        );
    }
}

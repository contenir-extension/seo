<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Api\Form\Form;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Api\Settings\SettingsSection;

/**
 * Holds how the site has changed the way its sections are described for search, in the local settings file.
 *
 * It has no screen or menu entry: the Strategy screen is its only editor, and writes the one field through
 * StrategyOverrides.
 *
 * @api
 */
final class StrategySection
{
    public const string NAME = 'seo-strategy';

    public const string SECTIONS = 'sections';

    /**
     * The section, kept out of the host's menus and screens.
     */
    public static function define(): SettingsSection
    {
        return new SettingsSection(
            name: self::NAME,
            title: 'Strategy',
            category: SettingsCategory::Seo,
            form: new Form([
                new Field(
                    name: self::SECTIONS,
                    label: 'Sections',
                    type: FieldType::Hidden,
                    local: true,
                ),
            ]),
            hidden: true,
        );
    }
}

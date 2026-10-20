<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Seo\Section\AppearanceSection;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_combine;
use function array_filter;
use function array_keys;
use function array_map;
use function count;

#[TestGroup('unit')]
final class AppearanceSectionTest extends TestCase
{
    /**
     * @template T
     *
     * @param callable(Field): T $read
     *
     * @return array<string, T>
     */
    private static function byName(callable $read): array
    {
        $fields = AppearanceSection::define()->form->fields;

        return array_combine(
            array_map(static fn(Field $field): string => $field->name, $fields),
            array_map($read, $fields),
        );
    }

    #[Test]
    public function describesEachFieldForEditors(): void
    {
        static::assertSame(
            [
                'site_name'         => [
                    'Site name',
                    'The name page titles and share previews give the site. Leave blank to use the name under Site.',
                ],
                'title_separator'   => ['Separator', 'Between the page title and the site name.'],
                'title_position'    => [
                    'Site name in titles',
                    'Where the site name goes in each page\'s title: "About us | Example".',
                ],
                'home_title'        => [
                    'Home page title',
                    'The home page\'s whole title, used as it is. Leave blank to build it like any other page\'s.',
                ],
                'description'       => [
                    'Default description',
                    'For search results and share previews of pages without a description of their own. Search'
                        . ' engines show about 160 characters.',
                ],
                'share_image'       => [
                    'Default share image',
                    'Shown when a page without an image of its own is shared. 1200 × 630 pixels works best.',
                ],
                'share_image_alt'   => [
                    'Share image description',
                    'What the share image shows, for people who can\'t see it.',
                ],
                'robots'            => [
                    'Search engines may',
                    'What search engines may do with the site\'s pages, unless a page says otherwise.',
                ],
                'organisation_name' => [
                    'Name',
                    'Who owns the site, as search engines describe it. Leave blank to use the site name.',
                ],
                'organisation_type' => ['Type', 'A local business has premises that customers visit.'],
                'organisation_logo' => ['Logo', 'A square logo of at least 112 × 112 pixels, on a plain background.'],
                'visibility'        => [
                    'Search engines',
                    'Saved for this environment only. Automatic hides every copy of the site that isn\'t at the site'
                        . ' URL, such as staging, so it never appears in search results.',
                ],
            ],
            self::byName(static fn(Field $field): array => [$field->label, $field->description]),
        );
    }

    #[Test]
    public function groupsTheFields(): void
    {
        static::assertSame(
            [
                ['titles', 'Titles', [['site_name'], ['title_separator', 'title_position'], ['home_title']]],
                ['defaults', 'Defaults', [['description'], ['share_image'], ['share_image_alt'], ['robots']]],
                ['organisation', 'Organisation', [['organisation_name'], ['organisation_type'], ['organisation_logo']]],
                ['environment', 'This environment', [['visibility']]],
            ],
            array_map(
                static fn(Group $group): array => [$group->id, $group->legend, $group->rows],
                AppearanceSection::define()->form->groups,
            ),
        );
    }

    #[Test]
    public function isSearchAppearanceUnderSeo(): void
    {
        $section = AppearanceSection::define();

        static::assertSame(
            ['seo', 'Search Appearance', SettingsCategory::Seo, [], [], null],
            [
                $section->name,
                $section->title,
                $section->category,
                $section->operations,
                $section->contributions,
                $section->order,
            ],
        );
    }

    #[Test]
    public function keepsTheEnvironmentsChoiceLocal(): void
    {
        static::assertSame(
            ['visibility'],
            array_keys(array_filter(self::byName(static fn(Field $field): bool => $field->local))),
        );
    }

    #[Test]
    public function offersChoicesWithDefaults(): void
    {
        static::assertSame(
            [
                'title_separator'   => ['select', '|', 7],
                'title_position'    => ['select', 'after', 3],
                'robots'            => ['select', 'index, follow', 4],
                'organisation_type' => ['select', 'Organization', 2],
                'visibility'        => ['select', 'auto', 3],
            ],
            array_filter(
                self::byName(static fn(Field $field): ?array => (
                    [] === $field->options
                        ? null
                        : [$field->type, $field->default, count($field->options)]
                )),
            ),
        );
    }

    #[Test]
    public function storesEachValueUnderItsKey(): void
    {
        static::assertSame(
            AppearanceSection::KEYS,
            self::byName(static fn(Field $field): ?string => $field->setting),
        );
    }

    #[Test]
    public function uploadsImagesToTheirOwnFolder(): void
    {
        static::assertSame(
            [
                'description'       => ['textarea', ['rows' => 3, 'maxlength' => 300]],
                'share_image'       => ['fileimage', ['path' => '/asset/img/seo']],
                'organisation_logo' => ['fileimage', ['path' => '/asset/img/seo']],
            ],
            array_filter(self::byName(static fn(Field $field): ?array => (
                [] === $field->attributes
                    ? null
                    : [$field->type, $field->attributes]
            ))),
        );
    }
}

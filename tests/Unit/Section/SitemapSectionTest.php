<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Api\Form\Field;
use Contenir\Extension\Api\Form\Group;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Operation\Contribution;
use Contenir\Extension\Api\Settings\SettingsCategory;
use Contenir\Extension\Seo\Section\SitemapSection;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[TestGroup('unit')]
final class SitemapSectionTest extends TestCase
{
    #[Test]
    public function checksAndDescribesEachField(): void
    {
        static::assertSame(
            [
                [
                    'sitemap',
                    'Publish a sitemap',
                    'checkbox',
                    'site.sitemap.enabled',
                    true,
                    [],
                    'At /sitemap.xml, named in robots.txt so search engines find every page, including those hidden'
                        . ' from the menu.',
                ],
                [
                    'sitemap_exclude',
                    'Leave out these workflows',
                    'seo:workflows',
                    'extensions.seo.sitemap_exclude',
                    null,
                    [],
                    'Kinds of page that shouldn\'t be listed, such as redirect or faq, separated by commas. Pages search'
                        . ' engines may not index are always left out.',
                ],
                [
                    'robots_rules',
                    'Extra robots.txt rules',
                    'seo:robots-rules',
                    'extensions.seo.robots_rules',
                    null,
                    ['rows' => 5],
                    'Added after "Allow: /", one per line, such as "Disallow: /search", or "User-agent: GPTBot"'
                        . ' followed by "Disallow: /" to keep one crawler out. A copy hidden from search engines'
                        . ' ignores them.',
                ],
            ],
            array_map(
                static fn(Field $field): array => [
                    $field->name,
                    $field->label,
                    $field->type,
                    $field->setting,
                    $field->default,
                    $field->attributes,
                    $field->description,
                ],
                SitemapSection::define(new Handle('seo'))->form->fields,
            ),
        );
    }

    #[Test]
    public function isSitemapUnderSeoWithALinkToTheSitemap(): void
    {
        $section = SitemapSection::define(new Handle('seo'));

        static::assertSame(
            [
                'seo-sitemap',
                'Sitemap',
                SettingsCategory::Seo,
                [
                    ['sitemap', 'Sitemap', [['sitemap'], ['sitemap_exclude']]],
                    ['robots', 'robots.txt', [['robots_rules']]],
                ],
                [['layout-nav', 'link', 'view', 'View XML sitemap', '', '/sitemap.xml', '']],
            ],
            [
                $section->name,
                $section->title,
                $section->category,
                array_map(static fn(Group $group): array => [
                    $group->id,
                    $group->legend,
                    $group->rows,
                ], $section->form->groups),
                array_map(
                    static fn(Contribution $link): array => [
                        $link->region,
                        $link->type,
                        $link->privilege,
                        $link->label,
                        $link->icon,
                        $link->url,
                        $link->content,
                    ],
                    $section->contributions,
                ),
            ],
        );
    }

    #[Test]
    public function listsTheKeyOfEachField(): void
    {
        static::assertSame(
            [
                'sitemap'         => 'site.sitemap.enabled',
                'sitemap_exclude' => 'extensions.seo.sitemap_exclude',
                'robots_rules'    => 'extensions.seo.robots_rules',
            ],
            SitemapSection::KEYS,
        );
    }
}

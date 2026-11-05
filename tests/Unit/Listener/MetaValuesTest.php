<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class MetaValuesTest extends TestCase
{
    private const array ROLES = [
        'title'       => ['meta_title', 'title'],
        'description' => ['description'],
        'image'       => ['image_lg'],
    ];

    /**
     * @param list<string> $fields
     * @param array<string, mixed> $values
     */
    private static function values(array $fields, array $values, ?string $advanced = 'seo'): MetaValues
    {
        return new MetaValues(
            new SectionBlock('a.b', new Element('seo'), $fields),
            $values,
            new SeoBlock('project', roles: self::ROLES, advanced: $advanced),
        );
    }

    #[Test]
    public function aNoteAboutAnImageBelongsToItsColumn(): void
    {
        static::assertSame('image_lg', self::values(['image_lg', 'seo'], [])->field('image'));
    }

    #[Test]
    public function aNoteAboutATitleBelongsToTheAdvancedField(): void
    {
        static::assertSame('seo', self::values(['title', 'seo'], [])->field('title'));
    }

    #[Test]
    public function aRoleTheAdvancedColumnDoesNotKeepGoesByItsColumns(): void
    {
        $values = self::values(['title', 'seo'], ['image_lg' => '/a.jpg', 'seo' => '{"image":"/b.jpg"}']);

        static::assertSame('/a.jpg', $values->text('image'));
    }

    #[Test]
    public function aRoleWithNoFieldBelongsToTheWholeRecord(): void
    {
        static::assertSame('', self::values([], [])->field('title'));
    }

    #[Test]
    public function aRoleWithoutTextHasNone(): void
    {
        static::assertNull(self::values([], ['title' => ['House']])->text('title'));
    }

    #[Test]
    public function aSectionWithoutTheAdvancedFieldHasNotesOnItsColumns(): void
    {
        static::assertSame('title', self::values(['title'], [])->field('title'));
    }

    #[Test]
    public function theAdvancedColumnsTitleWinsOverTheRecordsColumns(): void
    {
        $values = self::values(['title', 'seo'], ['title' => 'Column', 'seo' => '{"title":"Kept"}']);

        static::assertSame('Kept', $values->text('title'));
    }

    #[Test]
    public function withNothingKeptARoleTakesTheFirstColumnWithText(): void
    {
        $values = self::values(['title', 'seo'], ['meta_title' => ' ', 'title' => ' House ', 'seo' => null]);

        static::assertSame('House', $values->text('title'));
    }

    #[Test]
    public function withoutAnAdvancedColumnARoleGoesByItsColumns(): void
    {
        $values = self::values(['title'], ['title' => 'House', 'seo' => '{"title":"Kept"}'], null);

        static::assertSame('House', $values->text('title'));
    }
}

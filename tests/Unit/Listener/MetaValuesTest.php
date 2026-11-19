<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function str_repeat;
use function trim;

#[Group('unit')]
final class MetaValuesTest extends TestCase
{
    private const array ROLES = [
        'title'       => ['meta_title', 'title'],
        'description' => ['description'],
        'image'       => ['image_lg'],
    ];

    /**
     * @return array<string, array{string, string}>
     */
    public static function richTextDescriptionProvider(): array
    {
        $essay =
            '<p>We’re all guilty aren’t we? Of something? No matter how big or how small.</p> '
            . '<p>A white lie, a missed meeting, a forgotten call, an unseen message, an attempted try, '
            . 'a “no I didn’t”, a ‘borrowed’ item.</p>';

        return [
            'a short paragraph, without its markup'           => [
                '<p>A house by the sea, &amp; its garden.</p>',
                'A house by the sea, & its garden.',
            ],
            'a long body, cut by words, not after a sentence' => [
                $essay,
                'We’re all guilty aren’t we? Of something? No matter how big or how small. A white lie, a missed '
                    . 'meeting, a forgotten call, an unseen message, an attempted…',
            ],
        ];
    }

    private static function content(): string
    {
        return (string) json_encode([
            [
                'type'   => 'text',
                'status' => '1',
                'fields' => ['description' => '<p>A house by the sea, opened up to the garden.</p>'],
            ],
            ['type' => 'media', 'status' => '1', 'fields' => ['images' => ['3']]],
        ]);
    }

    /**
     * @param list<string> $fields
     * @param array<string, mixed> $values
     */
    private static function values(
        array $fields,
        array $values,
        ?string $advanced = 'seo',
        bool $automatic = true,
    ): MetaValues {
        return new MetaValues(
            new SectionBlock('a.b', new Element('seo'), $fields),
            $values,
            new SeoBlock('project', roles: self::ROLES, advanced: $advanced, automatic: $automatic),
        );
    }

    #[Test]
    public function aDescriptionColumnOfMarkupAloneHasNoText(): void
    {
        static::assertNull(self::values(['description'], ['description' => '<p> </p>'])->text('description'));
    }

    #[Test]
    public function aDescriptionColumnOfPlainTextIsKeptWhole(): void
    {
        $long = str_repeat('A plain description the editor wrote, ', times: 6);

        static::assertSame(
            trim($long),
            self::values(['description'], ['description' => $long])->text('description'),
        );
    }

    #[Test]
    #[DataProvider('richTextDescriptionProvider')]
    public function aDescriptionColumnOfRichTextGivesTheSummaryWrittenFromIt(string $column, string $shown): void
    {
        static::assertSame($shown, self::values(['description'], ['description' => $column])->text('description'));
    }

    #[Test]
    public function aManualSectionTakesNothingFromTheSectionFieldButStillSuggestsIt(): void
    {
        $values = self::values(['title', 'section'], ['section' => self::content()], automatic: false);

        static::assertSame(
            [null, false, null, 'A house by the sea, opened up to the garden.'],
            [
                $values->derivedDescription(),
                $values->hasDerivedImage(),
                $values->shownDescription(),
                $values->suggestedDescription(),
            ],
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
    public function aTitleColumnIsNotSummarised(): void
    {
        static::assertSame(
            '<em>Convoy</em>',
            self::values(['title'], ['title' => '<em>Convoy</em>'], advanced: null)->text('title'),
        );
    }

    #[Test]
    public function showsTheRecordsOwnDescriptionBeforeTheOneItsSectionFieldGives(): void
    {
        $values = self::values(['description', 'section'], [
            'description' => 'Own words.',
            'section'     => self::content(),
        ]);

        static::assertSame('Own words.', $values->shownDescription());
    }

    #[Test]
    public function showsTheSectionFieldsDescriptionWhereTheRecordHasNoneOfItsOwn(): void
    {
        $values = self::values(['description', 'section'], ['section' => self::content()]);

        static::assertSame('A house by the sea, opened up to the garden.', $values->shownDescription());
    }

    #[Test]
    public function takesADescriptionAndImageFromTheSectionFieldWhereTheColumnsGiveNone(): void
    {
        $values = self::values(['title', 'section'], ['section' => self::content()]);

        static::assertSame(
            ['A house by the sea, opened up to the garden.', true],
            [$values->derivedDescription(), $values->hasDerivedImage()],
        );
    }

    #[Test]
    public function takesNothingFromARecordWithNoSectionField(): void
    {
        $values = self::values(['title'], []);

        static::assertSame([null, false], [$values->derivedDescription(), $values->hasDerivedImage()]);
    }

    #[Test]
    public function takesNothingFromTheSectionFieldWhereTheColumnsGiveTheValue(): void
    {
        $values = self::values(['description', 'section'], [
            'description' => 'Own words.',
            'image_lg'    => '/a.jpg',
            'section'     => self::content(),
        ]);

        static::assertSame([null, false], [$values->derivedDescription(), $values->hasDerivedImage()]);
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

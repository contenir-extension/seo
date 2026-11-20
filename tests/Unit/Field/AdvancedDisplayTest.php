<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Field;

use Contenir\Extension\Api\Field\FieldDisplayLine;
use Contenir\Extension\Seo\Field\AdvancedDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('unit')]
final class AdvancedDisplayTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function imageProvider(): array
    {
        return [
            'an image of its own'    => ['/a.jpg', '/a.jpg'],
            "the site's share image" => ['@site', "The site's share image"],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function nothingStored(): array
    {
        return ['null' => [null], 'an empty string' => [''], 'not an object' => ['[1]']];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function ownColumns(): array
    {
        return ['the meta description' => ['meta_description'], 'the description' => ['description']];
    }

    private static function content(string $description): string
    {
        return (string) json_encode([['type' => 'text', 'status' => '1', 'fields' => ['description' => $description]]]);
    }

    #[Test]
    public function aColumnOfOnlySpacesHoldsNoDescription(): void
    {
        $lines = (new AdvancedDisplay())->of(null, [
            'meta_description' => '   ',
            'description'      => 12,
            'section'          => self::content('<p>From the content.</p>'),
        ]);

        static::assertEquals([new FieldDisplayLine('Description', 'From the content.', 'From content')], $lines);
    }

    #[Test]
    public function notesADescriptionTheSectionFieldGivesWhereNothingIsStored(): void
    {
        $lines = (new AdvancedDisplay())->of(
            (string) json_encode(['title' => 'Tea']),
            ['section' => self::content('<p>A cup of tea, poured slowly.</p>')],
        );

        static::assertEquals(
            [
                new FieldDisplayLine('Title', 'Tea'),
                new FieldDisplayLine('Description', 'A cup of tea, poured slowly.', 'From content'),
            ],
            $lines,
        );
    }

    #[Test]
    #[DataProvider('ownColumns')]
    public function saysNothingOfTheContentWhereTheRecordsOwnColumnsHoldADescription(string $column): void
    {
        $lines = (new AdvancedDisplay())->of(null, [
            $column   => 'Own.',
            'section' => self::content('<p>From the content.</p>'),
        ]);

        static::assertSame([], $lines);
    }

    #[Test]
    public function showsEachStoredValueByNameInTheOrderTheyAreKept(): void
    {
        $lines = (new AdvancedDisplay())->of(
            (string) json_encode([
                'schema'      => 'CreativeWork',
                'title'       => 'Tea',
                'robots'      => 'noindex',
                'description' => 'A cup.',
                'canonical'   => '/tea',
                'image_alt'   => 'A cup',
            ]),
            [],
        );

        static::assertEquals(
            [
                new FieldDisplayLine('Title', 'Tea'),
                new FieldDisplayLine('Description', 'A cup.'),
                new FieldDisplayLine('Share image description', 'A cup'),
                new FieldDisplayLine('Canonical address', '/tea'),
                new FieldDisplayLine('Robots', 'noindex'),
                new FieldDisplayLine('Schema type', 'CreativeWork'),
            ],
            $lines,
        );
    }

    #[Test]
    public function showsNoLineForAnEmptyField(): void
    {
        static::assertSame([], (new AdvancedDisplay())->of((string) json_encode(['title' => '  ']), []));
    }

    #[Test]
    #[DataProvider('nothingStored')]
    public function showsNoLineWhereNothingIsStoredAndTheContentGivesNoDescription(mixed $value): void
    {
        static::assertSame([], (new AdvancedDisplay())->of($value, ['section' => self::content('')]));
    }

    #[Test]
    #[DataProvider('imageProvider')]
    public function showsTheShareImageOverride(string $image, string $shown): void
    {
        static::assertEquals(
            [new FieldDisplayLine('Share image', $shown)],
            (new AdvancedDisplay())->of((string) json_encode(['image' => $image]), []),
        );
    }

    #[Test]
    public function showsTheStoredDescriptionWithoutANoteEvenWhereTheContentHasOne(): void
    {
        $lines = (new AdvancedDisplay())->of(
            (string) json_encode(['description' => 'Own.']),
            ['section' => self::content('<p>From the content.</p>')],
        );

        static::assertEquals([new FieldDisplayLine('Description', 'Own.')], $lines);
    }
}

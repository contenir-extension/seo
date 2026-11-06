<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Strategy;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Strategy\InvalidStrategy;
use Contenir\Extension\Seo\Strategy\SectionStrategy;
use Contenir\Extension\Seo\Strategy\StrategyEdit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class StrategyEditTest extends TestCase
{
    private const array FIELDS = [
        'title',
        'meta_title',
        'description',
        'meta_description',
        'summary',
        'image_lg',
        'alt_text',
    ];

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function refusedProvider(): array
    {
        return [
            'a schema type with spaces'  => [['schema' => 'Web Page'], '"Web Page" is not a schema.org type name.'],
            'a mode that is neither'     => [['mode' => 'sometimes'], 'The mode is automatic or manual.'],
            'a source the section lacks' => [
                ['sources' => ['title' => ['nope']]],
                '"nope" is not a field of Projects.',
            ],
            'an alt source it lacks'     => [['alt' => 'nope'], '"nope" is not a field of Projects.'],
        ];
    }

    #[Test]
    public function aFormThatSaysNothingOfARolesSourcesKeepsThem(): void
    {
        $block = $this->apply('<seo type="project"><title>title</title></seo>', ['schema' => 'Article']);

        static::assertSame('title', $block->children[0]->text);
    }

    #[Test]
    public function aFormThatSaysNothingOfTheModeKeepsIt(): void
    {
        static::assertSame(
            ['manual', null],
            [
                $this->apply('<seo type="project" mode="manual"/>', [])->attribute('mode'),
                $this->apply('<seo type="project"/>', [])->attribute('mode'),
            ],
        );
    }

    #[Test]
    public function aFormThatSaysNothingOfTheSchemaKeepsIt(): void
    {
        static::assertSame('Article', $this->apply('<seo type="project" schema="Article"/>', [])->attribute('schema'));
    }

    #[Test]
    public function anAltSourceTheBlockAlreadyReadsStaysEvenWhereTheSectionLacksTheField(): void
    {
        $block = $this->apply('<seo type="project"><image alt="hero.alt">image_lg</image></seo>', [
            'sources' => ['image' => ['image_lg']],
            'alt'     => 'hero.alt',
        ]);

        static::assertSame('hero.alt', $block->children[0]->attribute('alt'));
    }

    #[Test]
    public function aRoleAfterAnotherElementIsKept(): void
    {
        $block = $this->apply(
            '<seo type="project"><advanced>seo</advanced><title required="true">title</title></seo>',
            ['sources' => ['title' => ['title']]],
        );

        static::assertSame(
            ['advanced', 'title'],
            array_map(static fn(Element $child): string => $child->name, $block->children),
        );
    }

    #[Test]
    public function aRoleThatHadNoContentTakesItAgainWhenTheFormSaysSo(): void
    {
        $block = $this->apply(
            '<seo type="project"><description content="false">description</description></seo>',
            ['content' => ['description' => '1']],
        );

        static::assertSame([], $block->children[0]->attributes);
    }

    #[Test]
    public function aRoleTheFormLeavesAsTheBlockHadItIsNotWritten(): void
    {
        $block = $this->apply('<seo type="project"/>', [
            'sources' => [
                'title'       => ['meta_title', 'title'],
                'description' => ['meta_description', 'description'],
            ],
            'content' => ['description' => '1', 'image' => '1'],
        ]);

        static::assertSame([], $block->children);
    }

    #[Test]
    public function aRoleWithoutContentSaysSo(): void
    {
        $block = $this->apply('<seo type="project"/>', [
            'sources' => ['description' => ['meta_description', 'description']],
            'content' => ['description' => '0', 'image' => '1'],
        ]);

        static::assertSame(['description', 'false'], [
            $block->children[0]->name,
            $block->children[0]->attribute('content'),
        ]);
    }

    #[Test]
    public function aSourceTheBlockAlreadyReadsStaysEvenWhereTheSectionLacksTheField(): void
    {
        $block = $this->apply('<seo type="project"><image>assets[type=hero].path</image></seo>', [
            'sources' => ['image' => ['assets[type=hero].path', 'image_lg']],
            'content' => ['description' => '1', 'image' => '1'],
        ]);

        static::assertSame('assets[type=hero].path, image_lg', $block->children[0]->text);
    }

    #[Test]
    public function carriesOverWhatTheFormDoesNotSpeakFor(): void
    {
        $block = $this->apply(
            '<seo type="project"><title required="true">meta_title, title</title><advanced>seo</advanced></seo>',
            [
                'sources' => ['title' => ['title']],
                'content' => ['description' => '1', 'image' => '1'],
            ],
        );

        static::assertSame(
            [
                ['title',    'title', 'true'],
                ['advanced', 'seo',   null],
            ],
            array_map(
                static fn(Element $child): array => [$child->name, $child->text, $child->attribute('required')],
                $block->children,
            ),
        );
    }

    #[Test]
    public function leavesOutAnEmptySchemaTypeAndTheAutomaticMode(): void
    {
        $block = $this->apply('<seo type="project" schema="Article" mode="manual"/>', [
            'schema' => '',
            'mode'   => 'automatic',
        ]);

        static::assertSame([null, null], [$block->attribute('schema'), $block->attribute('mode')]);
    }

    /**
     * @param array<string, mixed> $post
     */
    #[Test]
    #[DataProvider('refusedProvider')]
    public function refusesWhatABlockCantHold(array $post, string $message): void
    {
        $this->expectException(InvalidStrategy::class);
        $this->expectExceptionMessage($message);

        $this->apply('<seo type="project"/>', $post);
    }

    #[Test]
    public function theContentSettingBelongsToTheDescriptionAndImageAlone(): void
    {
        $block = $this->apply('<seo type="project"><title>title</title></seo>', ['content' => ['title' => '0']]);

        static::assertSame([], $block->children[0]->attributes);
    }

    #[Test]
    public function trimsTheNamesOfTheSourcesAndOfTheAltText(): void
    {
        $block = $this->apply('<seo type="project"/>', [
            'sources' => ['image' => [' image_lg ']],
            'alt'     => ' alt_text ',
            'content' => ['description' => '1', 'image' => '1'],
        ]);

        static::assertSame(['image_lg', 'alt_text'], [
            $block->children[0]->text,
            $block->children[0]->attribute('alt'),
        ]);
    }

    #[Test]
    public function writesARolesSourcesInOrderAndDropsBlanksAndRepeats(): void
    {
        $block = $this->apply('<seo type="project"/>', [
            'sources' => ['description' => ['summary', '', 'description', 'summary']],
            'content' => ['description' => '1', 'image' => '1'],
        ]);

        static::assertSame(
            ['description'],
            array_map(static fn(Element $child): string => $child->name, $block->children),
        );
        static::assertSame('summary, description', $block->children[0]->text);
    }

    #[Test]
    public function writesTheAltTextSourceOnTheImageRole(): void
    {
        $block = $this->apply('<seo type="project"/>', [
            'sources' => ['image' => ['image_lg']],
            'alt'     => 'alt_text',
            'content' => ['description' => '1', 'image' => '1'],
        ]);

        static::assertSame(['image', 'image_lg', 'alt_text'], [
            $block->children[0]->name,
            $block->children[0]->text,
            $block->children[0]->attribute('alt'),
        ]);
    }

    #[Test]
    public function writesTheSchemaTypeAndModeAsAttributes(): void
    {
        $block = $this->apply('<seo type="project"/>', ['schema' => ' Article ', 'mode' => 'manual']);

        static::assertSame(['project', 'Article', 'manual'], [
            $block->attribute('type'),
            $block->attribute('schema'),
            $block->attribute('mode'),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function apply(string $xml, array $post): Element
    {
        $element = Element::parse($xml);

        return (new StrategyEdit())->apply(
            $element,
            SectionStrategy::of(new SectionBlock('library.projects.project', $element, self::FIELDS), 'Projects'),
            $post,
        );
    }
}

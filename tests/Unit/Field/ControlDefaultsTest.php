<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Field;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Field\ControlDefaults;
use Contenir\Seo\Head\ShareImage;
use Contenir\Seo\Robots\RobotsDirective;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\SiteUrl;
use Contenir\Seo\Title\TitleTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function str_repeat;
use function trim;

#[Group('unit')]
final class ControlDefaultsTest extends TestCase
{
    private const array ROLES = [
        'title'       => ['title'],
        'description' => ['description'],
        'image'       => ['image_lg'],
        'image@alt'   => ['caption'],
    ];

    /**
     * @return array<string, array{array<string, mixed>, ?SeoConfig, array{value: ?string, from: string, column: ?string}}>
     */
    public static function altProvider(): array
    {
        return [
            'its own column'               => [
                ['image_lg' => '/a.jpg', 'caption' => 'The house'],
                null,
                ['value' => 'The house', 'from' => 'column', 'column' => 'caption'],
            ],
            'none for its own image'       => [
                ['image_lg' => '/a.jpg'],
                null,
                ['value' => null, 'from' => 'none', 'column' => null],
            ],
            "the site image's description" => [
                ['caption' => 'Not used'],
                self::site(image: new ShareImage('/share.png', 'Word—Form')),
                ['value' => 'Word—Form', 'from' => 'site', 'column' => null],
            ],
            'a site image with none'       => [
                [],
                self::site(image: new ShareImage('/share.png', ' ')),
                ['value' => null, 'from' => 'none', 'column' => null],
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, ?SeoConfig, array{value: ?string, from: string, column: ?string}}>
     */
    public static function descriptionProvider(): array
    {
        $essay = '<p>' . str_repeat('Power is a word that evokes dominance. ', times: 6) . '</p>';

        return [
            'its own plain text'          => [
                ['description' => ' A house by the sea. '],
                null,
                ['value' => 'A house by the sea.', 'from' => 'column', 'column' => 'description'],
            ],
            'its long plain text, whole'  => [
                ['description' => str_repeat('An editor wrote this. ', times: 10)],
                null,
                [
                    'value'  => trim(str_repeat('An editor wrote this. ', times: 10)),
                    'from'   => 'column',
                    'column' => 'description',
                ],
            ],
            'its rich text, cut by words' => [
                ['description' => $essay],
                null,
                [
                    'value'  => 'Power is a word that evokes dominance. Power is a word that evokes dominance. Power is a word'
                        . ' that evokes dominance. Power is a word that evokes…',
                    'from'   => 'column',
                    'column' => 'description',
                ],
            ],
            'its Section field'           => [
                ['section' => self::content('<p>From the content.</p>')],
                null,
                ['value' => 'From the content.', 'from' => 'content', 'column' => null],
            ],
            "the site's description"      => [
                [],
                self::site('Every word.'),
                ['value' => 'Every word.', 'from' => 'site', 'column' => null],
            ],
            'a blank site description'    => [
                [],
                self::site('  '),
                ['value' => null, 'from' => 'none', 'column' => null],
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, ?SeoConfig, array{value: ?string, from: string, column: ?string}}>
     */
    public static function imageProvider(): array
    {
        $share = self::site(image: new ShareImage('https://www.example.com/share.png', 'Word—Form'));

        return [
            'its own image, as an address' => [
                ['image_lg' => '/asset/library/house.jpg'],
                $share,
                [
                    'value'  => 'https://www.example.com/asset/library/house.jpg',
                    'from'   => 'column',
                    'column' => 'image_lg',
                ],
            ],
            'its own image, kept as it is' => [
                ['image_lg' => 'house.jpg'],
                null,
                ['value' => 'house.jpg', 'from' => 'column', 'column' => 'image_lg'],
            ],
            'its Section field'            => [
                ['section' => self::contentWithAnImage()],
                $share,
                ['value' => null, 'from' => 'content', 'column' => null],
            ],
            "the site's share image"       => [
                [],
                $share,
                ['value' => 'https://www.example.com/share.png', 'from' => 'site', 'column' => null],
            ],
            'none at all'                  => [[], null, ['value' => null, 'from' => 'none', 'column' => null]],
        ];
    }

    private static function content(string $text): string
    {
        return (string) json_encode([['type' => 'text', 'status' => '1', 'fields' => ['description' => $text]]]);
    }

    private static function contentWithAnImage(): string
    {
        return (string) json_encode([['type' => 'media', 'status' => '1', 'fields' => ['images' => ['3']]]]);
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array{defaults: array<string, array{value: ?string, from: string, column: ?string}>, siteImage: ?array{url: string, alt: string}}
     */
    private static function of(array $record, ?SeoConfig $site = null): array
    {
        return ControlDefaults::of(
            new SectionBlock('site.page.home', new Element('seo'), [
                'title',
                'description',
                'image_lg',
                'caption',
                'seo',
                'section',
            ]),
            self::seo(),
            $record,
            $site ?? self::site(),
        );
    }

    private static function seo(): SeoBlock
    {
        return new SeoBlock('page', schema: 'Article', roles: self::ROLES, advanced: 'seo');
    }

    private static function site(string $description = '', ?ShareImage $image = null): SeoConfig
    {
        return new SeoConfig(
            site: new SiteUrl('https://www.example.com'),
            title: new TitleTemplate('Example'),
            description: $description,
            shareImage: $image,
            robots: RobotsDirective::NoindexFollow,
        );
    }

    #[Test]
    public function givesTheAddressRobotsAndSchemaFromThePageTheSiteAndTheSection(): void
    {
        $defaults = self::of([])['defaults'];

        static::assertSame(
            [
                ['value' => null, 'from' => 'page', 'column' => null],
                ['value' => 'noindex, follow', 'from' => 'site', 'column' => null],
                ['value' => 'Article', 'from' => 'section', 'column' => null],
            ],
            [$defaults['canonical'], $defaults['robots'], $defaults['schema']],
        );
    }

    /**
     * @param array<string, mixed>                                     $record
     * @param array{value: ?string, from: string, column: ?string} $expected
     */
    #[Test]
    #[DataProvider('descriptionProvider')]
    public function givesTheDescriptionFromWhereThePageTakesIt(array $record, ?SeoConfig $site, array $expected): void
    {
        static::assertSame($expected, self::of($record, $site)['defaults']['description']);
    }

    /**
     * @param array<string, mixed>                                     $record
     * @param array{value: ?string, from: string, column: ?string} $expected
     */
    #[Test]
    #[DataProvider('imageProvider')]
    public function givesTheShareImageFromWhereThePageTakesIt(array $record, ?SeoConfig $site, array $expected): void
    {
        static::assertSame($expected, self::of($record, $site)['defaults']['image']);
    }

    /**
     * @param array<string, mixed>                                     $record
     * @param array{value: ?string, from: string, column: ?string} $expected
     */
    #[Test]
    #[DataProvider('altProvider')]
    public function givesTheShareImagesDescriptionForTheImageInUse(
        array $record,
        ?SeoConfig $site,
        array $expected,
    ): void {
        static::assertSame($expected, self::of($record, $site)['defaults']['image_alt']);
    }

    #[Test]
    public function givesTheSitesShareImageForTheChoiceOfIt(): void
    {
        static::assertSame(
            [null, ['url' => '/share.png', 'alt' => 'Word—Form']],
            [
                self::of([])['siteImage'],
                self::of([], self::site(image: new ShareImage('/share.png', 'Word—Form')))['siteImage'],
            ],
        );
    }

    #[Test]
    public function givesTheTitleFromItsColumnBeforeTheSiteAddsItsName(): void
    {
        static::assertSame(
            ['value' => 'Jo Roca', 'from' => 'column', 'column' => 'title'],
            self::of(['title' => ' Jo Roca '])['defaults']['title'],
        );
    }

    #[Test]
    public function givesTheTitleNoneWithoutOne(): void
    {
        static::assertSame(['value' => null, 'from' => 'none', 'column' => null], self::of([])['defaults']['title']);
    }

    #[Test]
    public function ignoresTheRecordsOwnOverridesForItsDefaults(): void
    {
        static::assertSame(
            'Jo Roca',
            self::of(['title' => 'Jo Roca', 'seo' => '{"title":"Overridden"}'])['defaults']['title']['value'],
        );
    }

    #[Test]
    public function readsAColumnThatIsNotTextAsEmpty(): void
    {
        static::assertSame('none', self::of(['title' => ['x']])['defaults']['title']['from']);
    }
}

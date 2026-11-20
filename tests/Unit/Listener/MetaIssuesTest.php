<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaIssues;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Seo\Head\ShareImage;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\Title\TitleTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class MetaIssuesTest extends TestCase
{
    private const array ROLES = [
        'title'       => ['title'],
        'description' => ['description'],
        'image'       => ['image_lg'],
    ];

    /**
     * @return array<string, array{?SeoConfig, list<string>}>
     */
    public static function defaultsProvider(): array
    {
        return [
            'no site settings'                => [null, ['description', 'image']],
            'settings without defaults'       => [new SeoConfig(), ['description', 'image']],
            'a blank default description'     => [new SeoConfig(description: '  '), ['description', 'image']],
            'a default description'           => [new SeoConfig(description: 'Every word.'), ['image']],
            'a default share image'           => [
                new SeoConfig(shareImage: new ShareImage('/share.png')),
                ['description'],
            ],
            'a default description and image' => [
                new SeoConfig(
                    description: 'Every word.',
                    shareImage: new ShareImage('/share.png'),
                ),
                [],
            ],
        ];
    }

    /**
     * The roles a record with a title and nothing else is missing, under $site.
     *
     * @return list<string>
     */
    private static function missing(?SeoConfig $site): array
    {
        $seo    = new SeoBlock('page', roles: self::ROLES, advanced: null);
        $values = new MetaValues(
            new SectionBlock('a.b', new Element('seo'), ['title', 'description', 'image_lg']),
            ['title' => 'About'],
            $seo,
        );

        return array_map(
            static fn(MetaIssue $issue): string => $issue->role,
            (new MetaIssues($seo, new TitleTemplate('Example'), $site))->of($values),
        );
    }

    #[Test]
    public function aDefaultDoesNotExcuseARecordsOwnDescriptionBeingTooShort(): void
    {
        $seo    = new SeoBlock('page', roles: self::ROLES, advanced: null);
        $values = new MetaValues(
            new SectionBlock('a.b', new Element('seo'), ['title', 'description', 'image_lg']),
            ['title' => 'About', 'description' => 'Too short.', 'image_lg' => '/about.jpg'],
            $seo,
        );

        static::assertSame(
            ['description'],
            array_map(
                static fn(MetaIssue $issue): string => $issue->role,
                (new MetaIssues($seo, new TitleTemplate('Example'), new SeoConfig(description: 'Every word.')))->of(
                    $values,
                ),
            ),
        );
    }

    /**
     * @param list<string> $missing
     */
    #[Test]
    #[DataProvider('defaultsProvider')]
    public function theSitesDefaultsStandInForARecordsMissingDescriptionAndImage(?SeoConfig $site, array $missing): void
    {
        static::assertSame($missing, self::missing($site));
    }
}

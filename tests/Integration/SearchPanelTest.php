<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\Panel;
use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Panel\SearchPanel;
use Contenir\Extension\Seo\SeoExtension;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * The "Search and social" panel beside a saved record, on the API's
 * in-memory host.
 */
#[Group('integration')]
final class SearchPanelTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string URL = 'https://www.studioesteta.com.au/work/house';

    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    private InMemoryHost $host;

    /**
     * @return array<string, array{string, string}>
     */
    public static function descriptionProvider(): array
    {
        $cut = 'Ä' . str_repeat('é', times: 157) . ' ' . str_repeat('x', times: 50);

        return [
            'as long as search results show'    => [
                str_repeat('é', times: 160),
                'Description: ' . str_repeat('é', times: 160) . ' (160 of about 160 characters)',
            ],
            'cut off where search results stop' => [
                $cut,
                'Description: Ä' . str_repeat('é', times: 157) . '… (209 of about 160 characters)',
            ],
        ];
    }

    /**
     * @return array<string, array{mixed, list<string>}>
     */
    public static function discoverableProvider(): array
    {
        return [
            'switched off'    => ['0', ['Hidden from search: its Discoverable switch is off.']],
            'off as a number' => [0, ['Hidden from search: its Discoverable switch is off.']],
            'off as false'    => [false, ['Hidden from search: its Discoverable switch is off.']],
            'left empty'      => ['', ['Hidden from search: its Discoverable switch is off.']],
            'switched on'     => ['1', []],
        ];
    }

    #[Test]
    public function aPublishedRecordLinksToItsPageAndGooglesTest(): void
    {
        static::assertEquals(
            [
                new Link('View the page', self::URL),
                new Link(
                    'Test its structured data',
                    'https://search.google.com/test/rich-results?url=https%3A%2F%2Fwww.studioesteta.com.au%2Fwork%2Fhouse',
                ),
            ],
            $this->panel(['title' => 'House'], 4)?->links,
        );
    }

    #[Test]
    public function aRecordWithoutAPageHasNoLinks(): void
    {
        static::assertSame([], $this->panel(['title' => 'House'])?->links);
    }

    #[Test]
    public function aSectionWithoutABlockHasNoPanel(): void
    {
        static::assertNull((new SearchPanel())->panel(
            new RecordView('site.page.resource', 1, ['title' => 'Home']),
            $this->host->context('seo'),
        ));
    }

    #[Test]
    public function countsTheTitlesCharactersAsSearchResultsDo(): void
    {
        static::assertSame(
            'Title: Café | Studio Esteta (20 of about 60 characters)',
            $this->panel(['title' => 'Café'])?->paragraphs[0] ?? null,
        );
    }

    #[Test]
    public function listsTheFirstFewThingsToFix(): void
    {
        static::assertEquals(
            new Panel('Search and social', [
                'Title: none yet.',
                'Description: ' . str_repeat('a', times: 159) . '… (200 of about 160 characters)',
                'To fix: Add a title for search results.',
                'To fix: The description is 200 characters; search results cut it off after about 160.',
                'To fix: Add an image for shared links.',
            ]),
            $this->panel(['description' => str_repeat('a', times: 200)]),
        );
    }

    #[Test]
    public function readsTheDiscoverableSwitchTheBlockNames(): void
    {
        $this->host->block(
            'library.press.article',
            '<seo type="news"><discoverable>listed</discoverable></seo>',
            ['title', 'listed', 'visible'],
        );

        static::assertContains(
            'Hidden from search: its Discoverable switch is off.',
            (new SearchPanel())->panel(
                new RecordView('library.press.article', 1, ['title' => 'News', 'listed' => '0', 'visible' => '1']),
                $this->host->context('seo'),
            )->paragraphs ?? [],
        );
    }

    #[Test]
    public function saysWhenARecordHasNoDescriptionYet(): void
    {
        static::assertSame(
            'Description: none yet.',
            $this->panel(['title' => 'House', 'image_lg' => '/a.jpg'])?->paragraphs[1] ?? null,
        );
    }

    #[Test]
    #[DataProvider('discoverableProvider')]
    public function saysWhenTheRecordIsHiddenFromSearch(mixed $visible, array $hidden): void
    {
        static::assertEquals(
            [
                'Title: House | Studio Esteta (21 of about 60 characters)',
                'Description: ' . self::DESCRIPTION . ' (89 of about 160 characters)',
                ...$hidden,
            ],
            $this->panel([
                'title'       => 'House',
                'description' => self::DESCRIPTION,
                'image_lg'    => '/a.jpg',
                'visible'     => $visible,
            ])
                ?->paragraphs,
        );
    }

    #[Test]
    #[DataProvider('descriptionProvider')]
    public function showsTheDescriptionAsSearchResultsShowIt(string $description, string $shown): void
    {
        static::assertSame(
            $shown,
            $this->panel(['title' => 'House', 'description' => $description, 'image_lg' => '/a.jpg'])?->paragraphs[1]
                ?? null,
        );
    }

    protected function setUp(): void
    {
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')),
            new MapUrlResolver([self::SECTION . ':4' => self::URL]),
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->block(
            self::SECTION,
            '<seo type="project"><image alt="title">image_lg</image></seo>',
            ['title', 'meta_title', 'meta_description', 'description', 'image_lg', 'visible'],
        );
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Studio Esteta']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function panel(array $values, int $id = 1): ?Panel
    {
        return (new SearchPanel())->panel(new RecordView(self::SECTION, $id, $values), $this->host->context('seo'));
    }
}

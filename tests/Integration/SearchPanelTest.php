<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\Panel;
use Contenir\Extension\Api\Panel\PanelRow;
use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Api\Record\LinkableSection;
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

use function array_map;
use function str_contains;
use function str_repeat;

/**
 * The "SEO" panel beside a saved record, on the API's in-memory host: only what needs fixing, as short rows
 * with a Fix button, and an Actions menu.
 */
#[Group('integration')]
final class SearchPanelTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string URL = 'https://www.studioesteta.com.au/work/house';

    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    private InMemoryHost $host;

    /**
     * @return array<string, array{mixed, list<array{string, string}>}>
     */
    public static function discoverableProvider(): array
    {
        $hidden = [['Search', 'Hidden']];

        return [
            'switched off'    => ['0', $hidden],
            'off as a number' => [0, $hidden],
            'off as false'    => [false, $hidden],
            'left empty'      => ['', $hidden],
            'switched on'     => ['1', []],
        ];
    }

    #[Test]
    public function aPublishedRecordGetsItsPageAndGooglesTestInTheMenu(): void
    {
        static::assertEquals(
            [
                new Link('Preview in search', '/admin/extension/seo/preview?section=library.projects.project&id=4'),
                new Link('View the page', self::URL),
                new Link(
                    'Test its structured data',
                    'https://search.google.com/test/rich-results?url=https%3A%2F%2Fwww.studioesteta.com.au%2Fwork%2Fhouse',
                ),
            ],
            $this->panel(['title' => 'House'], 4)?->menu,
        );
    }

    #[Test]
    public function aReadyRecordSaysNothingAboutFixing(): void
    {
        $panel = $this->panel($this->ready());

        static::assertSame(['SEO', [], []], [$panel?->title, $panel?->paragraphs, $panel?->rows]);
    }

    #[Test]
    public function aRecordWithoutAPageOnlyOffersThePreview(): void
    {
        $menu = $this->panel(['title' => 'House'])->menu ?? [];

        static::assertSame(['Preview in search'], array_map(static fn(Link $link): string => $link->label, $menu));
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
    public function liesTheFixButtonsToTheRecordsEditScreen(): void
    {
        $rows = $this->panel(['title' => 'House'], 4)->rows ?? [];

        static::assertEquals(new Link('Fix', '/admin/records/library.projects.project/4'), $rows[0]->fix);
    }

    #[Test]
    public function listsWhatIsMissingOrOutsideTheLengthSearchShows(): void
    {
        $rows = $this->panel(['description' => str_repeat('a', times: 200)], 4)->rows ?? [];

        static::assertSame(
            [
                ['Title',       'Missing'],
                ['Share image', 'Missing'],
                ['Description', 'Too long (200 of about 160)'],
            ],
            array_map(static fn(PanelRow $row): array => [$row->label, $row->detail], $rows),
        );
    }

    #[Test]
    public function putsTheMostImportantFirst(): void
    {
        $rows = $this->panel(['title' => 'House', 'visible' => '0'], 4)->rows ?? [];

        static::assertSame(
            ['Description', 'Share image', 'Search'],
            array_map(static fn(PanelRow $row): string => $row->label, $rows),
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

        $rows = (new SearchPanel())->panel(
            new RecordView('library.press.article', 1, ['title' => 'News', 'listed' => '0', 'visible' => '1']),
            $this->host->context('seo'),
        )->rows ?? [];

        static::assertContains('Search', array_map(static fn(PanelRow $row): string => $row->label, $rows));
    }

    #[Test]
    #[DataProvider('discoverableProvider')]
    public function saysWhenTheRecordIsHiddenFromSearch(mixed $visible, array $hidden): void
    {
        $panel = $this->panel([...$this->ready(), 'visible' => $visible]);

        static::assertSame(
            $hidden,
            array_map(static fn(PanelRow $row): array => [$row->label, $row->detail], $panel->rows ?? []),
        );
    }

    #[Test]
    public function showsHowARecordReadsInSearch(): void
    {
        $panel = $this->panel($this->ready());

        static::assertSame(
            [true, true],
            [
                str_contains((string) $panel?->preview?->title, $this->ready()['title']),
                $this->ready()['description'] === $panel?->preview?->description,
            ],
        );
    }

    #[Test]
    public function showsNoShareImageBeforeTheSiteHasBeenAsked(): void
    {
        static::assertNull($this->panel($this->ready())?->image);
    }

    #[Test]
    public function titlesThePanelSeo(): void
    {
        static::assertInstanceOf(Panel::class, $this->panel(['title' => 'House']));
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
        $this->host->linkable(new LinkableSection(self::SECTION, 'Project'));
        $this->host->block(
            self::SECTION,
            '<seo type="project"><image alt="title">image_lg</image></seo>',
            ['title', 'meta_title', 'meta_description', 'description', 'image_lg', 'visible'],
        );
        $this->host->seed(self::SECTION, 4, ['title' => 'House']);
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Studio Esteta']);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function panel(array $values, int $id = 1): ?Panel
    {
        return (new SearchPanel())->panel(new RecordView(self::SECTION, $id, $values), $this->host->context('seo'));
    }

    /**
     * A record with a title, a description of the right length and a share image: nothing to fix.
     *
     * @return array<string, mixed>
     */
    private function ready(): array
    {
        return ['title' => 'House', 'description' => self::DESCRIPTION, 'image_lg' => '/a.jpg', 'visible' => '1'];
    }
}

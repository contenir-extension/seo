<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Module\RecordsHandler;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_map;
use function http_build_query;
use function implode;
use function iterator_to_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function str_repeat;
use function trim;

/**
 * The SEO area on the API's in-memory host: the Overview's counts and
 * site-wide checks, the Records report and its export, behind the area's
 * permission.
 */
#[Group('integration')]
final class SeoModuleTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    private const string NEWS = 'library.news.article';

    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    use TempDirectoryTrait;

    private InMemoryHost $host;

    /**
     * @return array<string, array{array<string, string|int>, list<string>}>
     */
    public static function filterProvider(): array
    {
        return [
            'everything'             => [[], ['House', 'house', 'Hut', 'Fish & chips']],
            'one section'            => [['section' => self::NEWS], ['Fish & chips']],
            'one role'               => [['role' => 'discoverable'], ['Hut']],
            'a section and role'     => [['section' => self::PROJECTS, 'role' => 'title'], ['House', 'house']],
            'a section nobody knows' => [['section' => 'nope'], ['House', 'house', 'Hut', 'Fish & chips']],
            'a role nobody knows'    => [['role' => 'nope'], ['House', 'house', 'Hut', 'Fish & chips']],
        ];
    }

    /**
     * @return array<string, array{list<string>, int}>
     */
    public static function guardProvider(): array
    {
        return [
            'the SEO area'   => [['seo'], 200],
            'to view only'   => [['seo:view'], 200],
            'to update only' => [['seo:update'], 403],
            'another area'   => [['forms'], 403],
            'nothing'        => [[], 403],
        ];
    }

    /**
     * @return array<string, array{array<string, int|string>, string, string, list<string>}>
     */
    public static function pageProvider(): array
    {
        return [
            'the first page'     => [[], '1', '50', ['pagination__next']],
            'a middle page'      => [['page' => 2], '51', '100', ['pagination__prev', 'pagination__next']],
            'the last page'      => [['page' => 3], '101', '120', ['pagination__prev']],
            'past the last page' => [['page' => 9], '101', '120', ['pagination__prev']],
            'before the first'   => [['page' => 0], '1', '50', ['pagination__next']],
            'not a number'       => [['page' => 'x'], '1', '50', ['pagination__next']],
        ];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function tabProvider(): array
    {
        return [
            'the Overview' => ['overview', 'Overview'],
            'the Records'  => ['records', 'Records'],
        ];
    }

    #[Test]
    public function aCountLeadsToThoseRecords(): void
    {
        $this->seedRecords();
        $hrefs = $this->texts($this->page('overview'), '//tbody/tr[1]/td[7]/a/@href');

        static::assertSame(
            ['/admin/extension/seo/records?section=library.projects.project&role=discoverable'],
            $hrefs,
        );
    }

    /**
     * @param array<string, int|string> $query
     * @param list<string> $links
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function aLongReportComesInPages(array $query, string $from, string $to, array $links): void
    {
        $this->seedLong(120);
        $xpath = $this->page('records', $query);

        static::assertSame(
            [[$from, $to, '120'], $links],
            [
                $this->texts($xpath, '//div[@class="pagination__text"]/strong'),
                $this->texts($xpath, '//nav[@class="pagination"]/a/@class'),
            ],
        );
    }

    #[Test]
    public function aLongSectionIsReadUpToItsLimit(): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        for ($id = 1; $id <= 5001; ++$id) {
            $this->host->seed(self::PROJECTS, $id, ['title' => "House {$id}", 'description' => self::DESCRIPTION]);
        }

        static::assertSame('5000', $this->texts($this->page('overview'), '//tbody/tr[1]/td[2]')[0] ?? null);
    }

    #[Test]
    public function aPageShowsAtMostItsShare(): void
    {
        $this->seedLong(120);

        static::assertCount(RecordsHandler::PER_PAGE, $this->page('records')->query('//tbody/tr'));
    }

    #[Test]
    public function aRecordLeadsToItsEditScreenAndItsPage(): void
    {
        $this->seedRecords();
        $xpath = $this->page('records', ['role' => 'discoverable']);

        static::assertSame(
            [
                '/admin/records/library.projects.project/3',
                '/admin/extension/seo/preview?section=library.projects.project&id=3',
                'https://www.example.com/work/hut',
            ],
            [...$this->texts($xpath, '//tbody/tr/td[1]/a/@href'), ...$this->texts($xpath, '//tbody/tr/td[4]/a/@href')],
        );
    }

    #[Test]
    public function aRecordWithoutAPageHasNoLinkToIt(): void
    {
        $this->seedRecords();

        static::assertCount(
            0,
            $this->page('records', ['section' => self::NEWS])->query('//tbody/tr/td[4]/a[@target]'),
        );
    }

    #[Test]
    public function aRoleFilterShowsOnlyThatRolesIssues(): void
    {
        $this->seedRecords();

        static::assertSame(
            ['Hidden from search: its Discoverable switch is off.'],
            $this->texts($this->page('records', ['role' => 'discoverable']), '//tbody/tr/td[3]/div'),
        );
    }

    #[Test]
    public function aSectionWithoutPagesIsLeftOut(): void
    {
        $this->seedRecords();
        $this->host->block('library.press.item', '<seo type="press"/>', ['title']);

        static::assertCount(2, $this->page('overview')->query('//tbody/tr'));
    }

    #[Test]
    public function aShortReportHasNoPageLinks(): void
    {
        $this->seedRecords();

        static::assertCount(0, $this->page('records')->query('//nav[@class="pagination"]'));
    }

    #[Test]
    public function aSiteWithNoSectionsSaysSo(): void
    {
        static::assertSame(
            ['No sections described yet'],
            $this->texts($this->page('overview'), '//h3[@class="empty__title"]'),
        );
    }

    #[Test]
    #[DataProvider('tabProvider')]
    public function eachScreenMarksItsOwnTab(string $handler, string $tab): void
    {
        $xpath = $this->page($handler);

        static::assertSame(
            [['Overview', 'Records'], [$tab]],
            [
                $this->texts($xpath, '//a[contains(@href, "/admin/extension/seo/") and (.="Overview" or .="Records")]'),
                $this->texts($xpath, '//a[@aria-current="page"]'),
            ],
        );
    }

    #[Test]
    #[DataProvider('guardProvider')]
    public function isGuardedByTheSeoArea(array $grants, int $status): void
    {
        static::assertSame($status, $this->get('overview', [], $grants)->getStatusCode());
    }

    #[Test]
    public function nothingToFixSaysSo(): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'House', 'description' => self::DESCRIPTION]);

        static::assertSame(
            ['Nothing to fix'],
            $this->texts($this->page('records'), '//h2[@class="empty__title"]'),
        );
    }

    #[Test]
    public function onlyPublishedRecordsAreReported(): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'Draft', 'status' => 'draft']);
        $this->host->seed(self::PROJECTS, 2, ['title' => 'Archive', 'status' => 'archived']);
        $this->host->seed(self::PROJECTS, 3, ['title' => 'Live']);

        static::assertSame(
            [['1'], ['Live']],
            [
                $this->texts($this->page('overview'), '//tbody/tr[1]/td[2]'),
                $this->texts($this->page('records'), '//tbody/tr/td[1]'),
            ],
        );
    }

    #[Test]
    public function pagesKeepTheFilter(): void
    {
        $this->seedLong(120);

        static::assertSame(
            ['/admin/extension/seo/records?section=library.projects.project&page=2'],
            $this->texts(
                $this->page('records', ['section' => self::PROJECTS]),
                '//a[@class="pagination__next"]/@href',
            ),
        );
    }

    #[Test]
    public function theExportKeepsARecordTitleFromRunningAsAFormula(): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        $this->host->seed(self::PROJECTS, 1, ['title' => '=HYPERLINK("x")', 'description' => 'Short.']);

        static::assertStringContainsString(
            "Projects,\"'=HYPERLINK(\"\"x\"\")\",Description",
            (string) $this->get('records-export')->getBody(),
        );
    }

    #[Test]
    public function theExportListsAnIssuePerLine(): void
    {
        $this->seedRecords();
        $response = $this->get('records-export', ['role' => 'discoverable']);

        static::assertSame(
            [
                'text/csv',
                "Section,Record,Area,Issue\nProjects,Hut,Hidden,\"Hidden from search: its Discoverable switch is off.\"\n",
            ],
            [$response->getHeaderLine('Content-Type'), (string) $response->getBody()],
        );
    }

    #[Test]
    public function theFiltersShowWhatIsChosen(): void
    {
        $this->seedRecords();
        $xpath = $this->page('records', ['section' => self::NEWS, 'role' => 'title']);

        static::assertSame(
            [self::NEWS, 'title'],
            $this->texts($xpath, '//option[@selected]/@value'),
        );
    }

    #[Test]
    public function theOverviewCountsEachSectionsRecordsByRole(): void
    {
        $this->seedRecords();
        $xpath = $this->page('overview');

        static::assertSame(
            [
                ['Projects',      '3', '0', '2', '1', '1', '1'],
                ['News articles', '1', '0', '0', '1', '0', '0'],
            ],
            [
                $this->texts($xpath, '//tbody/tr[1]/td'),
                $this->texts($xpath, '//tbody/tr[2]/td'),
            ],
        );
    }

    #[Test]
    public function theOverviewExplainsWhatToDoWithTheCounts(): void
    {
        static::assertStringContainsString(
            'Select a number to see those records',
            implode(' ', $this->texts($this->page('overview'), '//p[@class="card__description"]')),
        );
    }

    #[Test]
    public function theOverviewLeavesOutTheDefaultsOfASiteWithNoSeoBlocks(): void
    {
        $this->host->context('seo')->settings('seo-sitemap')->set(['sitemap' => false]);

        static::assertSame(
            [
                'Check The sitemap is off, so search engines only find pages by following links. Turn it on in Site'
                    . ' Settings, under Sitemap.',
            ],
            $this->texts($this->page('overview'), '//dd'),
        );
    }

    #[Test]
    public function theOverviewLinksToTheSettingsScreens(): void
    {
        static::assertSame(
            [
                '/admin/settings/common/view/category/seo/section/seo',
                '/admin/settings/common/view/category/seo/section/seo-verification',
                '/admin/settings/common/view/category/seo/section/seo-sitemap',
            ],
            $this->texts($this->page('overview'), '//nav[@aria-label="SEO settings"]/a/@href'),
        );
    }

    #[Test]
    public function theOverviewMarksSoundDefaultsOk(): void
    {
        static::assertSame(
            ['badge badge--success-muted'],
            $this->texts(
                $this->page('overview'),
                '//dt[.="Sitemap"]/following-sibling::dd/span/@class',
            ),
        );
    }

    #[Test]
    public function theOverviewSaysNothingOnceRecordsReachTheSite(): void
    {
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Site', 'records' => true]);

        static::assertSame([], $this->texts($this->page('overview'), '//p[contains(., "switched off")]'));
    }

    #[Test]
    public function theOverviewSaysWhenRecordsDoNotYetReachTheSite(): void
    {
        static::assertSame(
            ['/admin/settings/common/view/category/seo/section/seo'],
            $this->texts($this->page('overview'), '//p[contains(., "switched off")]/a/@href'),
        );
    }

    #[Test]
    public function theOverviewShowsHowTheSitesDefaultsStand(): void
    {
        $this->seedRecords();
        $this->host->context('seo')->settings('seo-sitemap')->set(['sitemap' => false]);

        static::assertSame(
            [
                'Check The site has no description, share image or organisation logo, so pages without their own have'
                    . ' nothing to fall back on. Add them in Site Settings, under Search Appearance.',
                'Check The sitemap is off, so search engines only find pages by following links. Turn it on in Site'
                    . ' Settings, under Sitemap.',
            ],
            $this->texts($this->page('overview'), '//dd'),
        );
    }

    /**
     * @param array<string, string|int> $query
     * @param list<string> $titles
     */
    #[Test]
    #[DataProvider('filterProvider')]
    public function theReportFiltersBySectionAndRole(array $query, array $titles): void
    {
        $this->seedRecords();

        static::assertSame($titles, $this->texts($this->page('records', $query), '//tbody/tr/td[1]'));
    }

    protected function setUp(): void
    {
        $this->makeTempDirectory();
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'https://www.example.com', 'title' => 'Site']],
        ]);
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')),
            new MapUrlResolver([self::PROJECTS . ':3' => 'https://www.example.com/work/hut']),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Site']);
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @param array<string, string|int> $query
     * @param list<string> $grants
     */
    private function get(string $handler, array $query = [], array $grants = ['seo']): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest(
            'GET',
            "/admin/extension/seo/{$handler}?" . http_build_query($query),
        )
            ->withQueryParams($query);

        return $this->host->handle($request, $grants);
    }

    /**
     * @param array<string, string|int> $query
     */
    private function page(string $handler, array $query = []): DOMXPath
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $body = (string) $this->get($handler, $query)->getBody();
        $document->loadHTML("<?xml encoding=\"UTF-8\">{$body}");
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    private function seedLong(int $count): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        for ($id = 1; $id <= $count; ++$id) {
            $this->host->seed(self::PROJECTS, $id, [
                'title'       => "House {$id}",
                'description' => str_repeat('a', times: 5),
            ]);
        }
    }

    /**
     * Projects with a shared title, a missing description and a hidden hut,
     * and a news article with a short description.
     */
    private function seedRecords(): void
    {
        $fields = ['title', 'meta_title', 'meta_description', 'description', 'image_lg', 'visible'];
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->linkable(new LinkableSection(self::NEWS, 'News article'));
        $this->host->block(
            self::PROJECTS,
            '<seo type="project"><image>image_lg</image><discoverable>visible</discoverable></seo>',
            $fields,
        );
        $this->host->block(self::NEWS, '<seo type="news"/>', $fields);
        $this->host->seed(self::PROJECTS, 1, [
            'title'       => 'House',
            'description' => self::DESCRIPTION,
            'image_lg'    => '/a.jpg',
            'visible'     => '1',
        ]);
        $this->host->seed(self::PROJECTS, 2, ['title' => ' house ', 'image_lg' => '/b.jpg', 'visible' => '1']);
        $this->host->seed(self::PROJECTS, 3, [
            'title'       => 'Hut',
            'description' => self::DESCRIPTION,
            'visible'     => '0',
        ]);
        $this->host->seed(self::NEWS, 4, ['title' => 'Fish & chips', 'description' => 'Short.']);
    }

    /**
     * @return list<string>
     */
    private function texts(DOMXPath $xpath, string $query): array
    {
        return array_map(
            static fn(DOMNode $node): string => trim((string) $node->textContent),
            iterator_to_array($xpath->query($query), preserve_keys: false),
        );
    }
}

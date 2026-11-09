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
            'the first page'     => [[], 'House 1', 50],
            'a middle page'      => [['page' => 2], 'House 51', 50],
            'the last page'      => [['page' => 3], 'House 101', 20],
            'past the last page' => [['page' => 9], 'House 101', 20],
            'before the first'   => [['page' => 0], 'House 1', 50],
            'not a number'       => [['page' => 'x'], 'House 1', 50],
        ];
    }

    #[Test]
    public function aCountLeadsToThoseRecords(): void
    {
        $this->seedRecords();
        $hrefs = $this->texts(
            $this->page('overview'),
            '(//li[@data-section])[1]//a[contains(@href, "role=discoverable")]/@href',
        );

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
    public function aLongReportComesInPages(array $query, string $first, int $rows): void
    {
        $this->seedLong(120);
        $xpath = $this->page('records', $query);

        static::assertSame(
            [$first, $rows, 3],
            [
                $this->texts($xpath, '//table/tr[2]/td[2]')[0] ?? null,
                $xpath->query('//table/tr[position()>1]')->length,
                $xpath->query('//table/following-sibling::a')->length,
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

        static::assertSame(
            '5000',
            $this->texts($this->page('overview'), '(//li[@data-section])[1]//span[@data-role="records"]')[0] ?? null,
        );
    }

    #[Test]
    public function aPageShowsAtMostItsShare(): void
    {
        $this->seedLong(120);

        static::assertCount(RecordsHandler::PER_PAGE, $this->page('records')->query('//table/tr[position()>1]'));
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
            [...$this->texts($xpath, '//table/tr/td/a/@href')],
        );
    }

    #[Test]
    public function aRecordWithoutAPageHasNoLinkToIt(): void
    {
        $this->seedRecords();

        static::assertCount(
            0,
            $this->page('records', ['section' => self::NEWS])->query('//table/tr/td[6]'),
        );
    }

    #[Test]
    public function aRoleFilterShowsOnlyThatRolesIssues(): void
    {
        $this->seedRecords();

        static::assertSame(
            ['Hidden from search: its Discoverable switch is off.'],
            $this->texts($this->page('records', ['role' => 'discoverable']), '//table/tr/td[4]'),
        );
    }

    #[Test]
    public function aSectionWithoutPagesIsLeftOut(): void
    {
        $this->seedRecords();
        $this->host->block('library.press.item', '<seo type="press"/>', ['title']);

        static::assertCount(2, $this->page('overview')->query('//li[@data-section]'));
    }

    #[Test]
    public function aShortReportHasNoPageLinks(): void
    {
        $this->seedRecords();

        static::assertCount(0, $this->page('records')->query('//table/following-sibling::a'));
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
            $this->texts($this->page('records'), '//div[@class="empty"]/h2'),
        );
    }

    #[Test]
    public function offersOnlyTheKindsOfIssueThatAreAboutARecordInTheFilter(): void
    {
        $this->seedRecords();

        static::assertSame(
            [
                '',
                'missing-title',
                'missing-description',
                'duplicate-title',
                'title-length',
                'description-length',
                'missing-image',
                'small-image',
                'hidden',
            ],
            $this->texts($this->page('records'), '//select[@name="kind"]/option/@value'),
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
                $this->texts($this->page('overview'), '(//li[@data-section])[1]//span[@data-role="records"]'),
                $this->texts($this->page('records'), '//table/tr/td[2]'),
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
                '//table/following-sibling::a[2]/@href',
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
            [$this->sectionRow($xpath, 1), $this->sectionRow($xpath, 2)],
        );
    }

    #[Test]
    public function theOverviewKeepsItsHeadingsToTheirNames(): void
    {
        static::assertSame(
            ['Overall rank', 'To do', 'Site setup'],
            array_map('trim', $this->texts($this->page('overview'), '//h2[@class="card__title"]/text()')),
        );
    }

    #[Test]
    public function theOverviewLeavesOutTheDefaultsOfASiteWithNoSeoBlocks(): void
    {
        $this->host->context('seo')->settings('seo-sitemap')->set(['sitemap' => false]);

        static::assertSame(
            [
                'The sitemap is off, so search engines only find pages by following links. Turn it on in Site'
                    . ' Settings, under Sitemap.',
            ],
            $this->texts($this->page('overview'), '//li[@data-setup and @data-setup="sitemap"]//p[@data-note]'),
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
            ['ok'],
            $this->texts($this->page('overview'), '//li[@data-setup="sitemap"]/@data-status'),
        );
    }

    #[Test]
    public function theOverviewNamesASectionByItsOwnHeading(): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project', heading: 'Our Projects'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'House']);

        static::assertSame(['Our Projects'], $this->texts($this->page('overview'), '//a[@data-role="name"]'));
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
            ['/admin/settings/common/update/category/seo/section/seo'],
            $this->texts($this->page('overview'), '//li[@data-setup="records"]//a/@href'),
        );
    }

    #[Test]
    public function theOverviewShowsHowTheSitesDefaultsStand(): void
    {
        $this->seedRecords();
        $this->host->context('seo')->settings('seo-sitemap')->set(['sitemap' => false]);

        static::assertSame(
            [
                'The site has no description, share image or organisation logo, so pages without their own have'
                    . ' nothing to fall back on. Add them in Site Settings, under Search Appearance.',
                'The sitemap is off, so search engines only find pages by following links. Turn it on in Site'
                    . ' Settings, under Sitemap.',
            ],
            $this->texts($this->page('overview'), '//li[@data-setup and @data-setup!="records"]//p[@data-note]'),
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

        static::assertSame($titles, $this->texts($this->page('records', $query), '//table/tr/td[2]'));
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

    private function page(string $handler, array $query = []): DOMXPath
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $body = (string) $this->get($handler, $query)->getBody();
        $document->loadHTML("<?xml encoding=\"UTF-8\">{$body}");
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * @param array<string, string|int> $query
     */
    /**
     * A section's row on the Overview as its name, published and checked counts, then how many records have
     * a title, description, image and hidden problem.
     *
     * @return list<string>
     */
    private function sectionRow(DOMXPath $xpath, int $position): array
    {
        $row  = "(//li[@data-section])[{$position}]";
        $role = static fn(string $name): string => "{$row}/@data-n-{$name}";

        return [
            ...$this->texts($xpath, "{$row}//a[@data-role=\"name\"]"),
            ...$this->texts($xpath, "{$row}//span[@data-role=\"records\"]"),
            ...$this->texts($xpath, "{$row}//span[@data-role=\"inspected\"]"),
            ...$this->texts($xpath, $role('title')),
            ...$this->texts($xpath, $role('description')),
            ...$this->texts($xpath, $role('image')),
            ...$this->texts($xpath, $role('discoverable')),
        ];
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

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Panel\Panel;
use Contenir\Extension\Api\Panel\PanelRow;
use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Site\SiteAnswer;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Inspect\InspectionStore;
use Contenir\Extension\Seo\Inspect\RecordInspections;
use Contenir\Extension\Seo\Inspect\Refreshed;
use Contenir\Extension\Seo\Inspect\RenderingState;
use Contenir\Extension\Seo\Inspect\SiteInspector;
use Contenir\Extension\Seo\Module\CheckHandler;
use Contenir\Extension\Seo\Panel\SearchPanel;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Task\InspectRecords;
use Contenir\Extension\Seo\Tests\TestAsset\Clock\TickingClock;
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
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;

use function array_keys;
use function array_map;
use function base64_decode;
use function count;
use function file_put_contents;
use function iterator_to_array;
use function json_encode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mkdir;
use function trim;

/**
 * Asking the site what it renders for a record, and the report and Preview
 * screen going by its answer.
 */
#[Group('integration')]
final class SiteInspectionTest extends TestCase
{
    /**
     * @return array<string, array{int, SiteAnswer|null, string, string}>
     */
    public static function checkProvider(): array
    {
        return [
            'every record answered'  => [
                1,
                new SiteAnswer(200, self::HEAD),
                'success',
                'Checked 1 record with the site.',
            ],
            'several answered'       => [
                2,
                new SiteAnswer(200, self::HEAD),
                'success',
                'Checked 2 records with the site.',
            ],
            'the site not answering' => [
                5,
                null,
                'warning',
                'Checked 0 records with the site. It gave no answer for 5.',
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function robotsProvider(): array
    {
        return [
            'its meta tag first' => [['robots' => 'index', 'robotsHeader' => 'noindex'], 'index'],
            'else its header'    => [['robotsHeader' => 'noindex'], 'noindex'],
            'neither'            => [[], 'None'],
        ];
    }

    /**
     * @return array<string, array{array<string, string>}>
     */
    public static function unknownProvider(): array
    {
        return [
            'nothing named'      => [[]],
            'no section'         => [['id' => '1']],
            'no record'          => [['section' => self::SECTION]],
            'an unknown section' => [['section' => 'nope', 'id' => '1']],
            'an unknown record'  => [['section' => self::SECTION, 'id' => '99']],
        ];
    }

    #[Test]
    public function aChangedRecordGoesBackToItsColumnsUntilAskedAgain(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->host->seed(self::SECTION, 1, ['title' => 'New title']);

        static::assertSame(
            ['Projects', '1', '0', '0', '1', '0', '0'],
            $this->texts($this->page('overview'), '//tbody/tr[1]/td[position()!=2]'),
        );
    }

    #[Test]
    public function aFreshCheckAsksAgainWhatWasAskedFiveMinutesAgo(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->ageAnswers(RecordInspections::RECENT);

        static::assertEquals(new Refreshed(1, 0, true), $this->refresh(RecordInspections::RECENT));
    }

    #[Test]
    public function aFreshCheckLeavesAloneWhatWasJustAsked(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->ageAnswers(RecordInspections::RECENT - 1);

        static::assertEquals(new Refreshed(0, 0, true), $this->refresh(RecordInspections::RECENT));
    }

    #[Test]
    public function aKeptAnswerForARecordThatIsGoneIsNotUsed(): void
    {
        $this->store()->save([self::SECTION . ':7' => new Inspection('s', 1, 'Old', null, 'https://x/old.jpg')]);

        static::assertNull($this->panel(['title' => 'Gone'], 7)?->image);
    }

    #[Test]
    public function aKeptAnswerGoesBackToTheColumnsOnceTheRecordsSwitchChanges(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->host->context('seo')->settings('seo')->set(['records' => true]);

        static::assertSame(
            ['Projects', '1', '0', '0', '1', '0', '0'],
            $this->texts($this->page('overview'), '//tbody/tr[1]/td[position()!=2]'),
        );
    }

    #[Test]
    public function anAnswerNotQuiteADayOldStands(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $kept = $this->store()->load()[self::SECTION . ':1'] ?? null;
        $this->store()->save([
            self::SECTION . ':1' => new Inspection(
                (string) $kept?->stamp,
                (int) $kept?->at - 86_399,
                null,
                null,
                null,
            ),
        ]);

        static::assertEquals(new Refreshed(0, 0, true), $this->refresh());
    }

    #[Test]
    public function anOrdinaryRoundLeavesAFiveMinuteOldAnswerAlone(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->ageAnswers(RecordInspections::RECENT);

        static::assertEquals(new Refreshed(0, 0, true), $this->refresh());
    }

    /**
     * @param array<string, string> $query
     */
    #[Test]
    #[DataProvider('unknownProvider')]
    public function aRecordThatCantBePreviewedGoesBack(array $query): void
    {
        $this->seed();

        static::assertSame(302, $this->get('preview', $query)->getStatusCode());
    }

    #[Test]
    public function aRecordWithoutAPageIsNotAsked(): void
    {
        $this->seed();

        static::assertSame(
            [null, []],
            [(new SiteInspector($this->host->context('seo')))->head($this->reference(9)), $this->host->sitePosts()],
        );
    }

    #[Test]
    public function aRefusalIsNoHead(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(403, self::HEAD));

        static::assertNull((new SiteInspector($this->host->context('seo')))->head($this->reference(1)));
    }

    #[Test]
    public function aRoundAsksAgainOnceAnAnswerIsADayOld(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $kept = $this->store()->load()[self::SECTION . ':1'] ?? null;
        $this->store()->save([
            self::SECTION . ':1' => new Inspection(
                (string) $kept?->stamp,
                (int) $kept?->at - 86_400,
                null,
                null,
                null,
            ),
        ]);

        static::assertEquals(new Refreshed(1, 0, true), $this->refresh());
    }

    #[Test]
    public function aRoundAsksAgainOnceTheRecordsSwitchChanges(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->host->context('seo')->settings('seo')->set(['records' => true]);

        static::assertEquals(new Refreshed(1, 0, true), $this->refresh());
    }

    #[Test]
    public function aRoundAsksAgainWhenARecordHasChanged(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->host->seed(self::SECTION, 1, ['title' => 'New title']);

        static::assertEquals(new Refreshed(1, 0, true), $this->refresh());
    }

    #[Test]
    public function aRoundCutShortKeepsWhatItHeard(): void
    {
        $this->boot(new TickingClock(1));
        $this->seed(5);
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $refreshed = (new RecordInspections($this->host->context('seo')))->refresh(3);

        static::assertSame(
            [false, $refreshed->checked],
            [$refreshed->finished, count($this->store()->load())],
        );
    }

    #[Test]
    public function aRoundGoesPastARecordWhoseAnswerStands(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->seed(2);

        static::assertEquals(new Refreshed(1, 0, finished: true), $this->refresh());
    }

    #[Test]
    public function aRoundKeepsWhatTheSiteAnswered(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));

        static::assertEquals(
            [new Refreshed(1, 0, true), [self::SECTION . ':1']],
            [$this->refresh(), array_keys($this->store()->load())],
        );
    }

    #[Test]
    public function aRoundLeavesAnAnswerThatStandsAlone(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();

        static::assertEquals(new Refreshed(0, 0, true), $this->refresh());
    }

    #[Test]
    public function aRoundWithNoTimeAsksNothing(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));

        static::assertEquals(
            [new Refreshed(0, 0, finished: false), []],
            [(new RecordInspections($this->host->context('seo')))->refresh(0), $this->host->sitePosts()],
        );
    }

    #[Test]
    public function asksTheSiteAboutARecordAtItsPath(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $head = (new SiteInspector($this->host->context('seo')))->head($this->reference(1));

        static::assertEquals(
            [
                'House | Site',
                [[
                    'record' => $this->reference(1),
                    'path'   => '/api/seo/inspect',
                    'fields' => ['path' => '/work/house'],
                ]],
            ],
            [$head?->title, $this->host->sitePosts()],
        );
    }

    #[Test]
    public function aSmallSourceImageIsReported(): void
    {
        $this->seed();
        mkdir("{$this->directory}/public/asset", recursive: true);
        file_put_contents("{$this->directory}/public/asset/a.png", (string) base64_decode(self::PIXEL, strict: true));
        $this->host->answerSite(
            SiteInspector::ENDPOINT,
            new SiteAnswer(
                200,
                (string) json_encode([
                    'status'      => 200,
                    'title'       => 'House | Site',
                    'description' => 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.',
                    'image'       => 'https://www.example.com/asset/_variant/share/a.png',
                ]),
            ),
        );
        $this->refresh();

        static::assertSame(
            ['The share image is 2×1, and sharing sites show about 1200×630, so it may look soft.'],
            $this->texts($this->page('records', ['role' => 'image']), '//tbody/tr/td[3]/div'),
        );
    }

    #[Test]
    public function aStoreThatIsNotJsonIsEmpty(): void
    {
        file_put_contents("{$this->directory}/data/cache/contenir-seo-inspections.json", data: 'not json');

        static::assertSame([], $this->store()->load());
    }

    #[Test]
    public function aStoreWithoutItsFolderKeepsNothing(): void
    {
        $store = new InspectionStore("{$this->directory}/missing/inspections.json");
        $store->save(['a' => new Inspection('s', 1, 't', 'd', 'i')]);

        static::assertSame([], $store->load());
    }

    #[Test]
    public function checkingNowAsksOnlyOnAPost(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->get('records-check', [], ['seo:update', 'seo']);

        static::assertSame([], $this->host->sitePosts());
    }

    #[Test]
    public function checkingNowNeedsToUpdate(): void
    {
        static::assertSame(403, $this->post('records-check', ['seo:view'])->getStatusCode());
    }

    #[Test]
    #[DataProvider('checkProvider')]
    public function checkingNowSaysHowItWent(int $records, ?SiteAnswer $answer, string $level, string $text): void
    {
        $this->seed($records);
        if (null !== $answer) {
            $this->host->answerSite(SiteInspector::ENDPOINT, $answer);
        }

        static::assertSame(
            [['level' => $level, 'text' => $text]],
            (new CheckHandler())->handle(
                (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/records-check'),
                $this->host->context('seo'),
            )->messages,
        );
    }

    #[Test]
    public function checkingNowSaysWhenTimeRanOut(): void
    {
        $this->boot(new TickingClock(30));
        $this->seed(2);
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));

        static::assertSame(
            [['level' => 'success', 'text' => 'Checked 0 records with the site. Check again for the rest.']],
            (new CheckHandler())->handle(
                (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/records-check'),
                $this->host->context('seo'),
            )->messages,
        );
    }

    #[Test]
    public function thePanelGoesBackToTheColumnsOnceARecordHasChanged(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();
        $this->host->seed(self::SECTION, 1, ['title' => 'New title']);

        static::assertNull($this->panel(['title' => 'New title'])?->image);
    }

    #[Test]
    public function thePanelGoesByTheColumnsBeforeTheSiteIsAsked(): void
    {
        $this->seed();

        static::assertSame([['Description', 'Missing']], $this->panelRows(['title' => 'House']));
    }

    #[Test]
    public function thePanelGoesByWhatTheSiteRendered(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();

        static::assertSame(
            [[], true],
            [$this->panelRows(['title' => 'House']), null !== $this->panel(['title' => 'House'])?->image],
        );
    }

    #[Test]
    public function thePanelListsWhatTheSiteLeftOut(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, '{"status":200,"title":"House | Site"}'));
        $this->refresh();

        static::assertSame(
            [
                ['Description', 'Missing'],
                ['Share image', 'Missing'],
            ],
            $this->panelRows(['title' => 'House']),
        );
    }

    #[Test]
    public function thePanelReadsItsOwnRecordsAnswer(): void
    {
        $this->seed(2);
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();

        static::assertSame(
            [[], 'House | Site'],
            [$this->panelRows(['title' => 'House 2'], 2), $this->panel(['title' => 'House 2'], 2)?->image?->alt],
        );
    }

    #[Test]
    public function thePreviewLaysOutTheStructuredData(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));

        static::assertSame(
            ["[\n    {\n        \"@type\": \"CreativeWork\"\n    }\n]"],
            $this->texts(
                $this->page('preview', ['section' => self::SECTION, 'id' => '1']),
                '//pre[contains(@class, "seo-preview__json-ld")]',
            ),
        );
    }

    #[Test]
    public function thePreviewLeavesAddressesAndAccentsReadable(): void
    {
        $this->seed();
        $this->host->answerSite(
            SiteInspector::ENDPOINT,
            new SiteAnswer(
                200,
                (string) json_encode([
                    'status' => 200,
                    'jsonLd' => [['name' => 'Café', 'url' => 'https://www.example.com/a']],
                ]),
            ),
        );

        $shown =
            $this->texts(
                $this->page('preview', ['section' => self::SECTION, 'id' => '1']),
                '//pre[contains(@class, "seo-preview__json-ld")]',
            )[0] ?? '';

        static::assertSame(
            ["[\n    {\n        \"name\": \"Café\",\n        \"url\": \"https://www.example.com/a\"\n    }\n]"],
            [$shown],
        );
    }

    #[Test]
    public function thePreviewLinksToTheRichResultsTest(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));

        static::assertSame(
            ['https://search.google.com/test/rich-results?url=https%3A%2F%2Fwww.example.com%2Fwork%2Fhouse'],
            $this->texts(
                $this->page('preview', ['section' => self::SECTION, 'id' => '1']),
                '//a[.="Test its structured data"]/@href',
            ),
        );
    }

    /**
     * @param array<string, string> $values
     */
    #[Test]
    #[DataProvider('robotsProvider')]
    public function thePreviewShowsTheRobotsRule(array $values, string $shown): void
    {
        $this->seed();
        $this->host->answerSite(
            SiteInspector::ENDPOINT,
            new SiteAnswer(200, (string) json_encode(['status' => 200, ...$values])),
        );

        static::assertSame(
            [$shown],
            $this->texts(
                $this->page('preview', ['section' => self::SECTION, 'id' => '1']),
                '//dt[.="Robots"]/following-sibling::dd',
            ),
        );
    }

    #[Test]
    public function thePreviewShowsWhatTheSiteRendered(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $xpath = $this->page('preview', ['section' => self::SECTION, 'id' => '1']);

        static::assertSame(
            [
                ['https://www.example.com/work/house'],
                ['House | Site'],
                ['https://www.example.com/a.jpg'],
                ['https://www.example.com/work/house', 'index,follow'],
                [],
            ],
            [
                $this->texts($xpath, '//p[contains(@class, "seo-preview__url")]'),
                $this->texts($xpath, '//p[contains(@class, "seo-preview__title")]'),
                $this->texts($xpath, '//img[contains(@class, "seo-preview__image")]/@src'),
                $this->texts($xpath, '//dd'),
                $this->texts($xpath, '//div[contains(@class, "alert")]'),
            ],
        );
    }

    #[Test]
    public function theRenderingStateNamesTheRecordsSwitch(): void
    {
        $context = $this->host->context('seo');
        $before  = RenderingState::of($context);
        $context->settings('seo')->set(['records' => true]);

        static::assertSame(['records:off', 'records:on'], [$before, RenderingState::of($context)]);
    }

    #[Test]
    public function theReportCountsEveryRecordTheSiteAnsweredFor(): void
    {
        $this->seed(2);
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $this->refresh();

        static::assertSame('2', $this->texts($this->page('overview'), '//tbody/tr[1]/td[4]')[0] ?? null);
    }

    #[Test]
    public function theReportGoesByTheSitesAnswer(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        $before = $this->texts($this->page('overview'), '//tbody/tr[1]/td[position()!=2]');
        $this->refresh();

        static::assertSame(
            [
                ['Projects', '1', '0', '0', '1', '0', '0'],
                ['Projects', '1', '1', '0', '0', '0', '0'],
            ],
            [$before, $this->texts($this->page('overview'), '//tbody/tr[1]/td[position()!=2]')],
        );
    }

    #[Test]
    public function theScheduledTaskRunsARound(): void
    {
        $this->seed();
        $this->host->answerSite(SiteInspector::ENDPOINT, new SiteAnswer(200, self::HEAD));
        (new InspectRecords())->run($this->host->context('seo'));

        static::assertCount(1, $this->host->sitePosts());
    }

    #[Test]
    public function theStoreDropsAnEntryOfAnotherShape(): void
    {
        file_put_contents(
            "{$this->directory}/data/cache/contenir-seo-inspections.json",
            (string) json_encode([
                'good'    => ['stamp' => 's', 'at' => 1, 'title' => 3],
                'nostamp' => ['at' => 1],
                'noat'    => ['stamp' => 's'],
                'strange' => 'x',
            ]),
        );

        static::assertEquals(['good' => new Inspection('s', 1, null, null, null)], $this->store()->load());
    }

    #[Test]
    public function theStoreReadsBackWhatItKept(): void
    {
        $kept = ['a:1' => new Inspection('s', 1, 't', null, 'i')];
        $this->store()->save($kept);

        static::assertEquals($kept, $this->store()->load());
    }

    #[Test]
    public function withoutTheSiteThePreviewShowsTheColumnsAndSaysSo(): void
    {
        $this->seed();
        $xpath = $this->page('preview', ['section' => self::SECTION, 'id' => '1']);

        static::assertSame(
            [
                [
                    'Approximate: the site didn\'t answer, so this shows the record\'s own columns, not what the site renders.',
                ],
                ['House'],
                ['No share image'],
                [],
            ],
            [
                $this->texts($xpath, '//div[contains(@class, "alert")]'),
                $this->texts($xpath, '//p[contains(@class, "seo-preview__title")]'),
                $this->texts($xpath, '//p[contains(@class, "seo-preview__no-image")]'),
                $this->texts($xpath, '//dd'),
            ],
        );
    }

    #[Test]
    public function withoutTheSiteThePreviewShowsTheRecordsDescription(): void
    {
        $this->host->seed(self::SECTION, 1, ['title' => 'House', 'description' => 'A weatherboard house.']);

        static::assertSame(
            ['A weatherboard house.'],
            $this->texts(
                $this->page('preview', ['section' => self::SECTION, 'id' => '1']),
                '//p[contains(@class, "seo-preview__description")]',
            ),
        );
    }

    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/data/cache", recursive: true);
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'https://www.example.com', 'title' => 'Site']],
        ]);
        $this->boot(new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')));
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function ageAnswers(int $seconds): void
    {
        $aged = [];
        foreach ($this->store()->load() as $key => $kept) {
            $aged[$key] = new Inspection(
                $kept->stamp,
                $kept->at - $seconds,
                $kept->title,
                $kept->description,
                $kept->image,
            );
        }

        $this->store()->save($aged);
    }

    private function boot(ClockInterface $clock): void
    {
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            $clock,
            new MapUrlResolver([
                self::SECTION . ':1' => 'https://www.example.com/work/house',
                self::SECTION . ':2' => 'https://www.example.com/work/2',
                self::SECTION . ':3' => 'https://www.example.com/work/3',
                self::SECTION . ':4' => 'https://www.example.com/work/4',
                self::SECTION . ':5' => 'https://www.example.com/work/5',
            ]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->linkable(new LinkableSection(self::SECTION, 'Project'));
        $this->host->block(
            self::SECTION,
            '<seo type="project"/>',
            ['title', 'meta_title', 'meta_description', 'description'],
        );
    }

    /**
     * @param array<string, string> $query
     * @param list<string> $grants
     */
    private function get(
        string $handler,
        array $query = [],
        array $grants = ['seo'],
    ): ResponseInterface {
        return $this->host->handle(
            (new Psr17Factory())->createServerRequest('GET', "/admin/extension/seo/{$handler}")
                ->withQueryParams($query),
            $grants,
        );
    }

    /**
     * @param array<string, string> $query
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

    /**
     * @param array<string, mixed> $values
     */
    private function panel(array $values, int $id = 1): ?Panel
    {
        return (new SearchPanel())->panel(new RecordView(self::SECTION, $id, $values), $this->host->context('seo'));
    }

    /**
     * What the panel says needs fixing, as label and detail.
     *
     * @param array<string, mixed> $values
     *
     * @return list<array{string, string}>
     */
    private function panelRows(array $values, int $id = 1): array
    {
        return array_map(
            static fn(PanelRow $row): array => [$row->label, $row->detail],
            $this->panel($values, $id)->rows ?? [],
        );
    }

    /**
     * @param list<string> $grants
     */
    private function post(string $handler, array $grants = ['seo']): ResponseInterface
    {
        return $this->host->handle(
            (new Psr17Factory())->createServerRequest('POST', "/admin/extension/seo/{$handler}"),
            $grants,
        );
    }

    use TempDirectoryTrait;

    private const string SECTION = 'library.projects.project';

    private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAAAAADRSSBWAAAAC0lEQVR4nGNgYAAAAAMAAbitOmMAAAAASUVORK5CYII=';

    private const string HEAD = '{"status":200,"title":"House | Site","description":"A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.","image":"https://www.example.com/a.jpg","imageAlt":"The house","canonical":"https://www.example.com/work/house","robots":"index,follow","jsonLd":[{"@type":"CreativeWork"}]}';

    private InMemoryHost $host;

    private function reference(int $id): RecordReference
    {
        return new RecordReference(self::SECTION, $id);
    }

    private function refresh(int $maxAge = RecordInspections::MAX_AGE): Refreshed
    {
        return (new RecordInspections($this->host->context('seo')))->refresh(50, $maxAge);
    }

    private function seed(int $count = 1): void
    {
        $this->host->seed(self::SECTION, 1, ['title' => 'House']);
        for ($id = 2; $id <= $count; ++$id) {
            $this->host->seed(self::SECTION, $id, ['title' => "House {$id}"]);
        }
    }

    private function store(): InspectionStore
    {
        return InspectionStore::at($this->directory);
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

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Site\SiteAnswer;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Inspect\RecordInspections;
use Contenir\Extension\Seo\Inspect\SiteInspector;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function mkdir;
use function str_repeat;

/**
 * What raises the "Records need attention" alert: a high or medium issue on a record shown to search, and
 * nothing else.
 */
#[Group('integration')]
final class RecordsAttentionTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    private const string READY = 'Every record in 1 section is ready for search.';

    private const string ONE = 'Records need attention for search: Projects: 1. See SEO in the menu.';

    private const string TWO = 'Records need attention for search: Projects: 2. See SEO in the menu.';

    private const array FIELDS = ['title', 'meta_title', 'description', 'image_lg', 'visible', 'searchable', 'seo'];

    use TempDirectoryTrait;

    private InMemoryHost $host;

    /**
     * @return array<string, array{string, list<array<string, string|null>>, string}>
     */
    public static function hiddenProvider(): array
    {
        $shown = ['title' => 'House', 'description' => self::DESCRIPTION];

        return [
            'by the switch its block names'                      => [
                '<seo type="project"><discoverable>searchable</discoverable></seo>',
                [['title' => 'Privacy', 'searchable' => '0']],
                self::READY,
            ],
            'by visible, where its block names no switch'        => [
                '<seo type="project"/>',
                [['title' => 'Privacy', 'visible' => '0']],
                self::READY,
            ],
            'by its own robots setting'                          => [
                '<seo type="project"><advanced>seo</advanced></seo>',
                [['title' => 'Privacy', 'seo' => '{"robots":"noindex, follow"}']],
                self::READY,
            ],
            'not by robots that only stop links being followed'  => [
                '<seo type="project"><advanced>seo</advanced></seo>',
                [['title' => 'Privacy', 'seo' => '{"robots":"index, nofollow"}']],
                self::ONE,
            ],
            'not while its switch holds nothing'                 => [
                '<seo type="project"/>',
                [['title' => 'Privacy', 'visible' => null]],
                self::ONE,
            ],
            'and its title is no duplicate of a shown record\'s' => [
                '<seo type="project"/>',
                [$shown, [...$shown, 'visible' => '0']],
                self::READY,
            ],
            'while two shown records with one title both count'  => [
                '<seo type="project"/>',
                [$shown, $shown, [...$shown, 'visible' => '0']],
                self::TWO,
            ],
        ];
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function issueProvider(): array
    {
        $ready = ['title' => 'House', 'description' => self::DESCRIPTION, 'image_lg' => '/a.jpg'];

        return [
            'nothing'                                => [$ready, self::READY],
            'a short description (low)'              => [[...$ready, 'description' => 'Short.'], self::READY],
            'a long description (low)'               => [
                [...$ready, 'description' => str_repeat('a', times: 200)],
                self::READY,
            ],
            'a title too long (medium)'              => [
                [...$ready, 'title' => str_repeat('a', times: 61)],
                self::ONE,
            ],
            'no share image (medium)'                => [
                ['title' => 'House', 'description' => self::DESCRIPTION],
                self::ONE,
            ],
            'a missing description (high)'           => [['title' => 'House', 'image_lg' => '/a.jpg'], self::ONE],
            'a missing title (high)'                 => [
                ['description' => self::DESCRIPTION, 'image_lg' => '/a.jpg'],
                self::ONE,
            ],
            'two issues on one record, counted once' => [['title' => str_repeat('a', times: 61)], self::ONE],
        ];
    }

    /**
     * @return array<string, array{array<string, string|null>, string}>
     */
    public static function pageProvider(): array
    {
        return [
            'a header of the page\'s own keeps it out' => [['robotsHeader' => 'noindex, follow'], self::READY],
            'a robots tag of the page\'s own does too' => [['robots' => 'noindex'], self::READY],
            'the header of a copy hidden as a whole'   => [['robotsHeader' => 'noindex, nofollow'], self::ONE],
            'a page that lets search in'               => [['robots' => 'index, follow'], self::ONE],
        ];
    }

    /**
     * @param list<array<string, string|null>> $records
     */
    #[Test]
    #[DataProvider('hiddenProvider')]
    public function aRecordHiddenFromSearchIsLeftOut(string $block, array $records, string $message): void
    {
        $this->host->block(self::SECTION, $block, self::FIELDS);
        foreach ($records as $index => $values) {
            $this->host->seed(self::SECTION, $index + 1, $values);
        }

        static::assertSame($message, $this->host->check('seo', 'records')->message);
    }

    /**
     * @param array<string, string|null> $head
     */
    #[Test]
    #[DataProvider('pageProvider')]
    public function aRecordWhosePageKeepsSearchOutIsLeftOut(array $head, string $message): void
    {
        $this->host->block(self::SECTION, '<seo type="project"/>', self::FIELDS);
        $this->host->seed(self::SECTION, 1, ['title' => 'Privacy']);
        $this->host->answerSite(
            SiteInspector::ENDPOINT,
            new SiteAnswer(200, (string) json_encode(['status' => 200, 'title' => 'Privacy | Site', ...$head])),
        );
        (new RecordInspections($this->host->context('seo')))->refresh(50);

        static::assertSame($message, $this->host->check('seo', 'records')->message);
    }

    #[Test]
    public function aSectionWithNoImageColumnIsNotAskedForAShareImage(): void
    {
        $this->host->block(self::SECTION, '<seo type="project"/>', self::FIELDS);
        $this->host->seed(self::SECTION, 1, ['title' => 'House', 'description' => self::DESCRIPTION]);
        $this->host->answerSite(
            SiteInspector::ENDPOINT,
            new SiteAnswer(
                200,
                (string) json_encode([
                    'status'      => 200,
                    'title'       => 'House | Site',
                    'description' => self::DESCRIPTION,
                ]),
            ),
        );
        (new RecordInspections($this->host->context('seo')))->refresh(50);

        static::assertSame(self::READY, $this->host->check('seo', 'records')->message);
    }

    /**
     * @param array<string, string> $values
     */
    #[Test]
    #[DataProvider('issueProvider')]
    public function onlyAHighOrMediumIssueRaisesTheAlert(array $values, string $message): void
    {
        $this->host->block(self::SECTION, '<seo type="project"><image>image_lg</image></seo>', self::FIELDS);
        $this->host->seed(self::SECTION, 1, $values);

        static::assertSame($message, $this->host->check('seo', 'records')->message);
    }

    #[Test]
    public function passesWhenOnlyAdviceAndHiddenRecordsAreLeft(): void
    {
        $this->host->block(self::SECTION, '<seo type="project"/>', self::FIELDS);
        $this->host->seed(self::SECTION, 1, ['title' => 'Terms', 'description' => 'Short.']);
        $this->host->seed(self::SECTION, 2, ['title' => 'Privacy', 'visible' => '0']);

        static::assertSame(Status::Success, $this->host->check('seo', 'records')->status);
    }

    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/data/cache", recursive: true);
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'https://www.example.com', 'title' => 'Site']],
        ]);
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')),
            new MapUrlResolver([self::SECTION . ':1' => 'https://www.example.com/privacy']),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Site']);
        $this->host->linkable(new LinkableSection(self::SECTION, 'Project'));
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}

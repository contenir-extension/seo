<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The dashboard checks on the records and the site's own defaults.
 */
#[Group('integration')]
final class RecordsCheckTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function aPartlyMissingSiteNamesWhatIsMissing(): void
    {
        $this->section();
        $this->host->context('seo')->settings('seo')->set(['description' => 'A studio.']);

        static::assertSame(
            'The site has no share image or organisation logo, so pages without their own have nothing to fall back'
                . ' on. Add them in Site Settings, under Search Appearance.',
            $this->host->check('seo', 'defaults')->message,
        );
    }

    #[Test]
    public function aReadySectionPasses(): void
    {
        $this->section();
        $this->host->seed(self::SECTION, 1, ['title' => 'House', 'description' => self::DESCRIPTION]);

        static::assertSame([Status::Success, 'Every record in 1 section is ready for search.'], $this->records());
    }

    #[Test]
    public function aSectionWithNothingToFixIsLeftOutOfTheCount(): void
    {
        $this->section();
        $this->host->seed(self::SECTION, 1, ['title' => 'House']);
        $this->host->linkable(new LinkableSection('library.news.article', 'News article'));
        $this->host->block('library.news.article', '<seo type="news"/>', ['title', 'description']);
        $this->host->seed('library.news.article', 2, ['title' => 'Opening', 'description' => self::DESCRIPTION]);

        static::assertSame(
            [Status::Warning, 'Records need attention for search: Projects: 1. See SEO in the menu.'],
            $this->records(),
        );
    }

    #[Test]
    public function aSiteWithEveryDefaultPasses(): void
    {
        $this->section();
        $this->host
            ->context('seo')
            ->settings('seo')
            ->set([
                'description'       => 'A studio.',
                'share_image'       => '/share.png',
                'organisation_logo' => '/logo.png',
            ]);

        static::assertSame(Status::Success, $this->host->check('seo', 'defaults')->status);
    }

    #[Test]
    public function aSiteWithNoSeoBlocksSkipsTheDefaultsCheck(): void
    {
        static::assertSame(Status::Skipped, $this->host->check('seo', 'defaults')->status);
    }

    #[Test]
    public function oneMissingDefaultIsNamedAlone(): void
    {
        $this->section();
        $this->host
            ->context('seo')
            ->settings('seo')
            ->set([
                'description'       => 'A studio.',
                'organisation_logo' => '/logo.png',
            ]);

        static::assertSame(
            'The site has no share image, so pages without their own have nothing to fall back on. Add it in Site'
                . ' Settings, under Search Appearance.',
            $this->host->check('seo', 'defaults')->message,
        );
    }

    #[Test]
    public function readySectionsPass(): void
    {
        $this->section();
        $this->host->seed(self::SECTION, 1, ['title' => 'House', 'description' => self::DESCRIPTION]);
        $this->host->linkable(new LinkableSection('library.news.article', 'News article'));
        $this->host->block('library.news.article', '<seo type="news"/>', ['title', 'description']);

        static::assertSame([Status::Success, 'Every record in 2 sections is ready for search.'], $this->records());
    }

    #[Test]
    public function recordsWithIssuesAreCountedBySection(): void
    {
        $this->section();
        $this->host->seed(self::SECTION, 1, ['title' => 'House']);
        $this->host->linkable(new LinkableSection('library.news.article', 'News article'));
        $this->host->block('library.news.article', '<seo type="news"/>', ['title', 'description']);
        $this->host->seed('library.news.article', 2, ['title' => 'Opening']);

        static::assertSame(
            [Status::Warning, 'Records need attention for search: Projects: 1; News articles: 1. See SEO in the menu.'],
            $this->records(),
        );
    }

    #[Test]
    public function theSitemapCheckPassesWhenItIsOn(): void
    {
        static::assertSame(Status::Success, $this->host->check('seo', 'sitemap')->status);
    }

    #[Test]
    public function theSitemapCheckWarnsWhenItIsOff(): void
    {
        $this->host->context('seo')->settings('seo-sitemap')->set(['sitemap' => false]);

        static::assertSame(Status::Warning, $this->host->check('seo', 'sitemap')->status);
    }

    #[Test]
    public function withNoSectionsThereIsNothingToCheck(): void
    {
        static::assertSame(
            [Status::Skipped, 'No section with pages of its own declares an <seo> block in admin.xml.'],
            $this->records(),
        );
    }

    #[Test]
    public function withoutASiteUrlTheDefaultsWaitOnIt(): void
    {
        $http = new Psr17Factory();
        $host = new InMemoryHost($http, $http, new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')));
        $host->boot(['seo' => new SeoExtension()], ['seo']);

        static::assertSame(Status::Skipped, $host->check('seo', 'defaults')->status);
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
            new MapUrlResolver(),
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
     * @return array{Status, string}
     */
    private function records(): array
    {
        $result = $this->host->check('seo', 'records');

        return [$result->status, $result->message];
    }

    private function section(): void
    {
        $this->host->linkable(new LinkableSection(self::SECTION, 'Project'));
        $this->host->block(self::SECTION, '<seo type="project"/>', ['title', 'meta_title', 'description']);
    }
}

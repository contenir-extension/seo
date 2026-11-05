<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Panel\SearchPanel;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * The title the save check and the panel measure: the site's, with the
 * site's own title where Search Appearance names no site name, as the site
 * itself writes it.
 */
#[Group('integration')]
final class SiteTitleTest extends TestCase
{
    use TempDirectoryTrait;

    private const string SECTION = 'library.projects.project';

    private InMemoryHost $host;

    #[Test]
    public function thePanelShowsTheTitleWithTheSitesOwnTitle(): void
    {
        static::assertSame(
            'Title: House | Studio Esteta (21 of about 60 characters)',
            (new SearchPanel())->panel(new RecordView(self::SECTION, 1, [
                'title' => 'House',
            ]), $this->host->context('seo'))
                ?->paragraphs[0] ?? null,
        );
    }

    #[Test]
    public function theSaveCheckCountsTheSitesOwnTitle(): void
    {
        static::assertSame(
            ['meta_title' => [
                'With the site name, the title is 61 characters; search results cut it off after about 60.',
            ]],
            $this->host->save(self::SECTION, 1, [
                'title'       => str_repeat('a', times: 45),
                'description' => str_repeat('d', times: 100),
                'image_lg'    => '/a.jpg',
            ])->warnings,
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'https://www.studioesteta.com.au', 'title' => 'Studio Esteta']],
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
        $this->host->block(
            self::SECTION,
            '<seo type="project"><image>image_lg</image></seo>',
            ['title', 'meta_title', 'meta_description', 'description', 'image_lg'],
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}

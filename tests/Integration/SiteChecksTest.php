<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Health\SiteChecks;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The site-wide checks as the site's health takes them.
 */
#[Group('integration')]
final class SiteChecksTest extends TestCase
{
    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function countsAMissingSitemapAndMissingDefaultsAsFailing(): void
    {
        $this->section();
        $this->host->context('seo')->settings('seo-sitemap')->set(['sitemap' => false]);

        static::assertSame(
            ['defaults-not-set' => true, 'sitemap-off' => true],
            SiteChecks::of($this->host->context('seo')),
        );
    }

    #[Test]
    public function countsWhatPassesAsNotFailing(): void
    {
        $this->section();
        $this->host
            ->context('seo')
            ->settings('seo')
            ->set(['description' => 'A studio.', 'share_image' => '/share.png', 'organisation_logo' => '/logo.png']);

        static::assertSame(
            ['defaults-not-set' => false, 'sitemap-off' => false],
            SiteChecks::of($this->host->context('seo')),
        );
    }

    #[Test]
    public function leavesOutTheDefaultsWhenNoSectionDeclaresSeo(): void
    {
        static::assertSame(['sitemap-off' => false], SiteChecks::of($this->host->context('seo')));
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

    private function section(): void
    {
        $this->host->linkable(new LinkableSection('library.projects.project', 'Project'));
        $this->host->block('library.projects.project', '<seo type="project"/>', ['title', 'description']);
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Module\MeterSegment;
use Contenir\Extension\Api\Module\MeterTone;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Module\DashboardMeter;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

#[Group('integration')]
#[Group('dashboard')]
final class DashboardMeterTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function answersNothingForASiteWithNoSeoBlocks(): void
    {
        static::assertNull((new DashboardMeter())->meter($this->host->context('seo')));
    }

    #[Test]
    public function dividesTheBarByImpactLeavingOutWhatHasNoIssues(): void
    {
        $this->seedProjects();
        $meter = (new DashboardMeter())->meter($this->host->context('seo'));

        static::assertSame(
            [
                ['High impact', MeterTone::Poor],
                ['Low impact',  MeterTone::Neutral],
            ],
            array_map(static fn(MeterSegment $one): array => [$one->label, $one->tone], $meter->segments ?? []),
        );
    }

    #[Test]
    public function namesTheCardSeoRanking(): void
    {
        $this->seedProjects();

        static::assertSame('SEO ranking', (new DashboardMeter())->meter($this->host->context('seo'))?->heading);
    }

    #[Test]
    public function readsAHealthySiteAsGood(): void
    {
        $this->host
            ->context('seo')
            ->settings('seo')
            ->set([
                'site_name'         => 'Site',
                'description'       => 'A studio.',
                'share_image'       => '/asset/share.jpg',
                'organisation_logo' => '/asset/logo.png',
            ]);
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'House', 'description' => str_repeat('a', times: 100)]);
        $meter = (new DashboardMeter())->meter($this->host->context('seo'));

        static::assertSame(['100', 'Good', MeterTone::Good], [$meter?->value, $meter?->label, $meter?->tone]);
    }

    #[Test]
    public function readsTheScoreAndWhatItMeans(): void
    {
        $this->seedProjects();
        $meter = (new DashboardMeter())->meter($this->host->context('seo'));

        static::assertSame(['Needs work', MeterTone::Warning], [$meter?->label, $meter?->tone]);
    }

    #[Test]
    public function saysThatTheSiteHasNotBeenCheckedYet(): void
    {
        $this->seedProjects();

        static::assertSame(
            'Not checked with the site yet',
            (new DashboardMeter())->meter($this->host->context('seo'))?->caption,
        );
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
            new MapUrlResolver([]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Site']);
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function seedProjects(): void
    {
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(self::PROJECTS, '<seo type="project"/>', ['title', 'meta_title', 'description']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'House', 'description' => 'Short.']);
        $this->host->seed(self::PROJECTS, 2, ['title' => 'Hut']);
    }
}

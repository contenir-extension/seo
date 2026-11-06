<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Inspect\InspectionStore;
use Contenir\Extension\Seo\Module\OverviewHandler;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function mkdir;

#[Group('integration')]
final class OverviewAgeTest extends TestCase
{
    private const int NOW = 1_793_955_600;

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function saysHowLongAgoTheSiteWasLastAsked(): void
    {
        InspectionStore::at($this->directory)->save([
            'a:1' => new Inspection('s', self::NOW - 3000, 'A', 'A.', null),
            'a:2' => new Inspection('s', self::NOW - 120, 'B', 'B.', null),
            'a:3' => new Inspection('s', self::NOW - 600, 'C', 'C.', null),
        ]);

        static::assertSame('2 minutes', OverviewHandler::checkedAgo($this->host->context('seo')));
    }

    #[Test]
    public function saysNothingWhenTheSiteWasNeverAsked(): void
    {
        static::assertNull(OverviewHandler::checkedAgo($this->host->context('seo')));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/data/cache", recursive: true);
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock((new DateTimeImmutable())->setTimestamp(self::NOW)),
            new MapUrlResolver([]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}

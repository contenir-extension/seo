<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\Change;
use Contenir\Extension\Seo\Bulk\ChangePlan;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('integration')]
#[Group('bulk')]
final class ChangePlanTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    private const string NEWS = 'library.news.article';

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function hidesOnlyTheRecordsThatAreShown(): void
    {
        static::assertSame(
            [[1, 'visible', '1', '0']],
            $this->summary($this->plan(FixKind::HideFromSearch, [1, 2, 3])),
        );
    }

    #[Test]
    public function leavesOutARecordAlreadyAsAsked(): void
    {
        static::assertSame([], $this->plan(FixKind::ShowInSearch, [1]));
    }

    #[Test]
    public function leavesOutARecordThatHasADescription(): void
    {
        static::assertSame([], $this->plan(FixKind::WriteDescriptions, [2]));
    }

    #[Test]
    public function leavesOutARecordWithNothingToWriteFrom(): void
    {
        static::assertSame([], $this->plan(FixKind::WriteDescriptions, [3]));
    }

    #[Test]
    public function showsARecordThatIsHidden(): void
    {
        static::assertSame([[3, 'visible', '0', '1']], $this->summary($this->plan(FixKind::ShowInSearch, [3])));
    }

    #[Test]
    public function writesADescriptionFromTheSummaryIntoTheDescriptionColumn(): void
    {
        static::assertSame(
            [[1, 'meta_description', null, 'A weatherboard house in Northcote.']],
            $this->summary($this->plan(FixKind::WriteDescriptions, [1, 2, 3])),
        );
    }

    #[Test]
    public function writesTheDescriptionIntoTheAdvancedColumnWhenTheBlockHasOne(): void
    {
        $changes = (new ChangePlan($this->host->context('seo')->sections()))->build(
            FixKind::WriteDescriptions,
            [new RecordReference(self::NEWS, 5), new RecordReference(self::NEWS, 6)],
        );

        static::assertSame(
            [[5, 'seo', 'description', null, 'A short post.']],
            array_map(
                static fn(Change $change): array => [
                    $change->record->id,
                    $change->column,
                    $change->key,
                    $change->before,
                    $change->after,
                ],
                $changes,
            ),
        );
    }

    #[Override]
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
            new FrozenClock(new DateTimeImmutable('2026-11-06T09:00:00+00:00')),
            new MapUrlResolver([]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(
            self::PROJECTS,
            '<seo type="project"><discoverable>visible</discoverable></seo>',
            ['title', 'meta_description', 'description', 'summary', 'visible'],
        );
        $this->host->seed(self::PROJECTS, 1, [
            'title'   => 'House',
            'summary' => '<p>A weatherboard house in Northcote.</p>',
            'visible' => '1',
        ]);
        $this->host->seed(self::PROJECTS, 2, [
            'title'            => 'Shed',
            'meta_description' => 'A shed.',
            'summary'          => 'A big shed.',
            'visible'          => '0',
        ]);
        $this->host->seed(self::PROJECTS, 3, ['title' => 'Hut', 'visible' => '0']);
        $this->host->linkable(new LinkableSection(self::NEWS, 'News'));
        $this->host->block(
            self::NEWS,
            '<seo type="news"><advanced>seo</advanced></seo>',
            ['title', 'summary', 'seo'],
        );
        $this->host->seed(self::NEWS, 5, [
            'title'   => 'Post',
            'summary' => 'A short post.',
            'seo'     => '{"robots":"noindex"}',
        ]);
        $this->host->seed(self::NEWS, 6, [
            'title'   => 'Kept',
            'summary' => 'Another post.',
            'seo'     => '{"description":"Already here."}',
        ]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @param list<int> $ids
     *
     * @return list<Change>
     */
    private function plan(FixKind $kind, array $ids): array
    {
        return (new ChangePlan($this->host->context('seo')->sections(), [self::PROJECTS => 'Projects']))->build(
            $kind,
            array_map(static fn(int $id): RecordReference => new RecordReference(self::PROJECTS, $id), $ids),
        );
    }

    /**
     * @param list<Change> $changes
     *
     * @return list<array{string|int, string, ?string, string}>
     */
    private function summary(array $changes): array
    {
        return array_map(
            static fn(Change $change): array => [$change->record->id, $change->column, $change->before, $change->after],
            $changes,
        );
    }
}

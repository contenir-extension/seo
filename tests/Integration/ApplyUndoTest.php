<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Queue\TaskQueueInterface;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Bulk\ApplyChanges;
use Contenir\Extension\Seo\Bulk\Run;
use Contenir\Extension\Seo\Bulk\RunChange;
use Contenir\Extension\Seo\Bulk\RunStore;
use Contenir\Extension\Seo\Bulk\UndoChanges;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RunFactory;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function mkdir;

#[Group('integration')]
#[Group('bulk')]
final class ApplyUndoTest extends TestCase
{
    private const int NOW = 1_793_955_600;

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function appliesTheChangesAndFinishesTheRun(): void
    {
        $this->keep(RunFactory::make(at: self::NOW - 60));
        $this->queue(ApplyChanges::NAME);

        static::assertSame(
            [Run::FINISHED, [RunChange::DONE], 'A weatherboard house.'],
            [$this->kept()->state, $this->statuses(), $this->description(1)],
        );
    }

    #[Test]
    public function cannotBeUndoneTwice(): void
    {
        $this->applied();
        $this->queue(UndoChanges::NAME);
        $this->host->save(RunFactory::SECTION, 1, ['meta_description' => 'Written again.']);
        $this->queue(UndoChanges::NAME);

        static::assertSame('Written again.', $this->description(1));
    }

    #[Test]
    public function doesNotMakeAChangeTwice(): void
    {
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: [
                RunFactory::change(1, RunChange::DONE, after: 'Already done.'),
            ],
        ));
        $this->queue(ApplyChanges::NAME);

        static::assertSame(null, $this->description(1));
    }

    #[Test]
    public function leavesAChangeAloneWhenTheRecordHasChangedSince(): void
    {
        $this->applied();
        $this->host->save(RunFactory::SECTION, 1, ['meta_description' => 'Rewritten by hand.']);
        $this->queue(UndoChanges::NAME);

        static::assertSame(
            [[RunChange::SKIPPED], 'Rewritten by hand.'],
            [$this->statuses(), $this->description(1)],
        );
    }

    #[Test]
    public function leavesTheRunAloneOnceItsDayIsUp(): void
    {
        $this->applied(at: self::NOW - Run::UNDO_WINDOW - 1);
        $this->queue(UndoChanges::NAME);

        static::assertSame([[RunChange::DONE], 'A weatherboard house.'], [$this->statuses(), $this->description(1)]);
    }

    #[Test]
    public function putsBackWhatTheRunReplaced(): void
    {
        $this->applied();
        $this->queue(UndoChanges::NAME);

        static::assertSame([[RunChange::UNDONE], '', self::NOW], [
            $this->statuses(),
            $this->description(1),
            $this->kept()->undoneAt,
        ]);
    }

    #[Override]
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
            new FrozenClock((new DateTimeImmutable())->setTimestamp(self::NOW)),
            new MapUrlResolver([]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->linkable(new LinkableSection(RunFactory::SECTION, 'Project'));
        $this->host->block(
            RunFactory::SECTION,
            '<seo type="project"/>',
            ['title', 'meta_title', 'meta_description', 'description'],
        );
        $this->host->seed(RunFactory::SECTION, 1, ['title' => 'House 1']);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function applied(?int $at = null): void
    {
        $this->keep(RunFactory::make(at: $at ?? (self::NOW - 60)));
        $this->queue(ApplyChanges::NAME);
        $this->keep($this->kept()->with($this->kept()->changes, Run::FINISHED));
    }

    private function description(int $id): ?string
    {
        $value = $this->host->stored(RunFactory::SECTION, $id)['meta_description'] ?? null;

        return null === $value ? null : (string) $value;
    }

    private function keep(Run $run): void
    {
        RunStore::at($this->directory)->put($run);
    }

    private function kept(): Run
    {
        $run = RunStore::at($this->directory)->find('run-1');
        static::assertInstanceOf(Run::class, $run);

        return $run;
    }

    private function queue(string $task): void
    {
        $queue = $this->host->context('seo')->services()->get(TaskQueueInterface::class);
        static::assertInstanceOf(TaskQueueInterface::class, $queue);
        $queue->enqueue('seo', $task, ['run' => 'run-1']);
        $this->host->runQueued();
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return array_map(static fn(RunChange $change): string => $change->status, $this->kept()->changes);
    }
}

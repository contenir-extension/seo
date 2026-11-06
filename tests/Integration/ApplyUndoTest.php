<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Closure;
use Contenir\Extension\Api\Queue\TaskQueueInterface;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
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
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\UpdateSpy;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function mkdir;

#[Group('integration')]
#[Group('bulk')]
final class ApplyUndoTest extends TestCase
{
    private const int NOW = 1_793_955_600;

    use TempDirectoryTrait;

    private InMemoryHost $host;

    private static function advanced(int $id): RunChange
    {
        return RunFactory::change($id, section: RunFactory::ADVANCED, column: 'seo', key: 'description');
    }

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
    public function holdsBackAChangeWhoseAdvancedColumnIsNotJson(): void
    {
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: [self::advanced(2)],
        ));
        $this->queue(ApplyChanges::NAME);

        static::assertSame(
            [[RunChange::STOPPED], 'not json'],
            [$this->statuses(), $this->stored(RunFactory::ADVANCED, 2, 'seo')],
        );
    }

    #[Test]
    public function keepsAChangeAlreadyDoneBesideOnesStillWaiting(): void
    {
        $this->host->seed(RunFactory::SECTION, 2, ['title' => 'House 2']);
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: [
                RunFactory::change(1, RunChange::DONE, after: 'Already done.'),
                RunFactory::change(2),
            ],
        ));
        $this->queue(ApplyChanges::NAME);

        static::assertSame(
            [
                [1,               2],
                [RunChange::DONE, RunChange::DONE],
                [null,            'A weatherboard house.'],
            ],
            [
                array_map(static fn(RunChange $change): int|string => $change->record->id, $this->kept()->changes),
                $this->statuses(),
                [$this->description(1), $this->description(2)],
            ],
        );
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
    public function putsBackEveryChangeOfALongRunNotJustTheLastChunk(): void
    {
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: $this->manyChanges(51),
        ));
        $this->queue(ApplyChanges::NAME);
        $this->keep($this->kept()->with($this->kept()->changes, Run::FINISHED));
        $this->queue(UndoChanges::NAME);

        static::assertSame(
            [51, 51],
            [count($this->kept()->changes), $this->kept()->count(RunChange::UNDONE)],
        );
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

    #[Test]
    public function recordsWhatARecordHeldBeforeAChangeIsMade(): void
    {
        $this->host->seed(RunFactory::SECTION, 2, ['title' => 'House 2', 'meta_description' => 'Old words.']);
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: [RunFactory::change(2)],
        ));
        $this->queue(ApplyChanges::NAME);

        static::assertSame(['Old words.'], array_map(
            static fn(RunChange $change): ?string => $change->before,
            $this->kept()->changes,
        ));
    }

    #[Test]
    public function setsADescriptionIntoTheAdvancedColumnBesideItsOtherSettings(): void
    {
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: [self::advanced(1)],
        ));
        $this->queue(ApplyChanges::NAME);

        static::assertSame(
            ['{"robots":"noindex","description":"A weatherboard house."}', null],
            [$this->stored(RunFactory::ADVANCED, 1, 'seo'), $this->kept()->changes[0]->before],
        );
    }

    #[Test]
    public function showsTheRunAsRunningAndWhatItHasSavedWhileItsChunksAreWritten(): void
    {
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: $this->manyChanges(51),
        ));
        $seen = [];
        $this->spyOnUpdates(function () use (&$seen): void {
            $seen[] = [$this->kept()->state, $this->kept()->count(RunChange::DONE)];
        });
        $this->queue(ApplyChanges::NAME);

        static::assertSame(
            [[Run::RUNNING, 0], [Run::RUNNING, 50], Run::FINISHED, 51, 51],
            [...$seen, $this->kept()->state, count($this->kept()->changes), $this->kept()->count(RunChange::DONE)],
        );
    }

    #[Test]
    public function takesTheDescriptionBackOutOfTheAdvancedColumnOnUndo(): void
    {
        $this->keep(RunFactory::make(
            at: self::NOW - 60,
            changes: [self::advanced(1)],
        ));
        $this->queue(ApplyChanges::NAME);
        $this->keep($this->kept()->with($this->kept()->changes, Run::FINISHED));
        $this->queue(UndoChanges::NAME);

        static::assertSame('{"robots":"noindex"}', $this->stored(RunFactory::ADVANCED, 1, 'seo'));
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
        $this->host->linkable(new LinkableSection(RunFactory::ADVANCED, 'News'));
        $this->host->block(RunFactory::ADVANCED, '<seo type="news"><advanced>seo</advanced></seo>', ['title', 'seo']);
        $this->host->seed(RunFactory::ADVANCED, 1, ['title' => 'Post', 'seo' => '{"robots":"noindex"}']);
        $this->host->seed(RunFactory::ADVANCED, 2, ['title' => 'Odd', 'seo' => 'not json']);
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

    /**
     * @return list<RunChange>
     */
    private function manyChanges(int $count): array
    {
        $changes = [];
        for ($id = 100; $id < (100 + $count); ++$id) {
            $this->host->seed(RunFactory::SECTION, $id, ['title' => "House {$id}"]);
            $changes[] = RunFactory::change($id);
        }

        return $changes;
    }

    private function queue(string $task): void
    {
        $queue = $this->host->context('seo')->services()->get(TaskQueueInterface::class);
        static::assertInstanceOf(TaskQueueInterface::class, $queue);
        $queue->enqueue('seo', $task, ['run' => 'run-1']);
        $this->host->runQueued();
    }

    /**
     * @param Closure(): void $before
     */
    private function spyOnUpdates(Closure $before): void
    {
        $real = $this->host->context('seo')->services()->get(RecordUpdatesInterface::class);
        static::assertInstanceOf(RecordUpdatesInterface::class, $real);
        $this->host->boot([], [], [RecordUpdatesInterface::class => new UpdateSpy($real, $before)]);
    }

    /**
     * @return list<string>
     */
    private function statuses(): array
    {
        return array_map(static fn(RunChange $change): string => $change->status, $this->kept()->changes);
    }

    private function stored(string $section, int $id, string $column): ?string
    {
        $value = $this->host->stored($section, $id)[$column] ?? null;

        return null === $value ? null : (string) $value;
    }
}

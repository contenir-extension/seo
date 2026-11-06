<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\Run;
use Contenir\Extension\Seo\Bulk\RunChange;
use Contenir\Extension\Seo\Bulk\RunCodec;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RunFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class RunTest extends TestCase
{
    /**
     * @return array<string, array{Run, int, bool}>
     */
    public static function undoableProvider(): array
    {
        $done = [RunFactory::change(status: RunChange::DONE)];

        $finished = RunFactory::make(
            state: Run::FINISHED,
            changes: $done,
        );
        $running = RunFactory::make(
            state: Run::RUNNING,
            changes: $done,
        );
        $undone = RunFactory::make(
            state: Run::FINISHED,
            changes: $done,
            undoneAt: 1_500,
        );
        $stopped = RunFactory::make(
            state: Run::FINISHED,
            changes: [RunFactory::change(status: RunChange::STOPPED)],
        );

        return [
            'just finished'         => [$finished, 1_000, true],
            'at the end of the day' => [$finished, 1_000 + Run::UNDO_WINDOW, true],
            'a day and a second on' => [$finished, 1_001 + Run::UNDO_WINDOW, false],
            'still being applied'   => [$running, 1_000, false],
            'undone already'        => [$undone, 1_600, false],
            'nothing was saved'     => [$stopped, 1_000, false],
        ];
    }

    #[Test]
    #[DataProvider('undoableProvider')]
    public function canBeUndoneWhileItsValuesAreFresh(Run $run, int $now, bool $expected): void
    {
        static::assertSame($expected, $run->undoable($now));
    }

    #[Test]
    public function countsTheChangesInAState(): void
    {
        $run = RunFactory::make(changes: [
            RunFactory::change(1, RunChange::DONE),
            RunFactory::change(2, RunChange::STOPPED),
            RunFactory::change(3, RunChange::DONE),
        ]);

        static::assertSame([2, 1, 0], [
            $run->count(RunChange::DONE),
            $run->count(RunChange::STOPPED),
            $run->count(RunChange::FAILED),
        ]);
    }

    #[Test]
    public function isNotReadFromAnEntryOfAnotherShape(): void
    {
        static::assertNull(RunCodec::decode(['id' => 'run-1', 'at' => 'yesterday', 'kind' => 'hide']));
    }

    #[Test]
    public function isReadBackAsItWasKept(): void
    {
        $run = RunFactory::make(
            state: Run::FINISHED,
            changes: [
                RunFactory::change(1, RunChange::DONE, before: 'Old.'),
                RunFactory::change(2, RunChange::SKIPPED),
            ],
            undoneAt: 2_000,
        );

        static::assertEquals($run, RunCodec::decode(RunCodec::encode($run)));
    }

    #[Test]
    public function keepsAChangesMessageThroughStorage(): void
    {
        $run = RunFactory::make(changes: [RunFactory::change(1, RunChange::STOPPED)->with(
            RunChange::STOPPED,
            'Held back.',
        )]);

        static::assertSame('Held back.', RunCodec::decode(RunCodec::encode($run))?->changes[0]->message);
    }

    #[Test]
    public function keepsWhenItWasUndoneThroughAChangeOfState(): void
    {
        $run = RunFactory::make(
            state: Run::FINISHED,
            undoneAt: 2_000,
        )->with([], Run::FINISHED);

        static::assertSame(2_000, $run->undoneAt);
    }

    #[Test]
    public function recordsWhenItWasUndoneOverAnEarlierTime(): void
    {
        $run = RunFactory::make(
            state: Run::FINISHED,
            undoneAt: 2_000,
        )->with([], Run::FINISHED, undoneAt: 3_000);

        static::assertSame(3_000, $run->undoneAt);
    }
}

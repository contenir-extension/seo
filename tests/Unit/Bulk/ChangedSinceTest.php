<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\ChangedSince;
use Contenir\Extension\Seo\Bulk\Run;
use Contenir\Extension\Seo\Bulk\RunChange;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RecordingSections;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RunFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
#[Group('bulk')]
final class ChangedSinceTest extends TestCase
{
    #[Test]
    public function leavesAChangeThatWasNeverSavedAsItWas(): void
    {
        $sections = new RecordingSections(['meta_description' => 'Something else.']);
        $run      = RunFactory::make(
            state: Run::FINISHED,
            changes: [RunFactory::change(1, RunChange::STOPPED)],
        );
        $marked = (new ChangedSince($sections))->mark($run);

        static::assertSame(
            [RunChange::STOPPED],
            array_map(static fn(RunChange $change): string => $change->status, $marked),
        );
    }

    #[Test]
    public function leavesASavedChangeWhoseRecordStillHoldsWhatItWrote(): void
    {
        $sections = new RecordingSections(['meta_description' => 'A weatherboard house.']);
        $run      = RunFactory::make(
            state: Run::FINISHED,
            changes: [RunFactory::change(1, RunChange::DONE)],
        );
        $marked = (new ChangedSince($sections))->mark($run);

        static::assertSame(
            [RunChange::DONE],
            array_map(static fn(RunChange $change): string => $change->status, $marked),
        );
    }

    #[Test]
    public function marksASavedChangeWhoseRecordHoldsSomethingElseNow(): void
    {
        $sections = new RecordingSections(['meta_description' => 'Rewritten by hand.']);
        $run      = RunFactory::make(
            state: Run::FINISHED,
            changes: [RunFactory::change(1, RunChange::DONE)],
        );
        $marked = (new ChangedSince($sections))->mark($run);

        static::assertSame([[RunChange::SKIPPED, ChangedSince::MESSAGE]], array_map(
            static fn(RunChange $change): array => [$change->status, $change->message],
            $marked,
        ));
    }

    #[Test]
    public function readsOnlyTheRecordsOfTheChangesThatWereSaved(): void
    {
        $sections = new RecordingSections(['meta_description' => 'A weatherboard house.']);
        $run      = RunFactory::make(
            state: Run::FINISHED,
            changes: [
                RunFactory::change(1, RunChange::DONE),
                RunFactory::change(2, RunChange::STOPPED),
                RunFactory::change(3, RunChange::PENDING),
            ],
        );
        (new ChangedSince($sections))->mark($run);

        static::assertSame([[1]], array_map(static fn(array $asked): array => $asked[2], $sections->asked));
    }
}

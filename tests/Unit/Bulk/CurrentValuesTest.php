<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\CurrentValues;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RecordingSections;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RunFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class CurrentValuesTest extends TestCase
{
    #[Test]
    public function asksForEachColumnOnceHoweverManyChangesNameIt(): void
    {
        $sections = new RecordingSections();
        (new CurrentValues($sections))->of([
            RunFactory::change(1),
            RunFactory::change(2),
            RunFactory::change(3, column: 'seo', key: 'description'),
        ]);

        static::assertSame(['meta_description', 'seo'], $sections->asked[0][1]);
    }

    #[Test]
    public function asksOneSectionAtATimeForTheRecordsItsChangesName(): void
    {
        $sections = new RecordingSections();
        (new CurrentValues($sections))->of([
            RunFactory::change(1),
            RunFactory::change(2, section: RunFactory::ADVANCED),
            RunFactory::change(3),
        ]);

        static::assertSame(
            [[RunFactory::SECTION, [1, 3]], [RunFactory::ADVANCED, [2]]],
            [
                [$sections->asked[0][0], $sections->asked[0][2]],
                [$sections->asked[1][0], $sections->asked[1][2]],
            ],
        );
    }

    #[Test]
    public function keysAChangesValueBySectionRecordAndColumn(): void
    {
        static::assertSame('library.projects.project|1|meta_description', CurrentValues::key(RunFactory::change(1)));
    }

    #[Test]
    public function readsEachChangesColumnForEachRecordItNames(): void
    {
        $sections = new RecordingSections(['meta_description' => 'Held.']);
        $values   = (new CurrentValues($sections))->of([RunFactory::change(1), RunFactory::change(2)]);

        static::assertSame(
            [
                'library.projects.project|1|meta_description' => 'Held.',
                'library.projects.project|2|meta_description' => 'Held.',
            ],
            $values,
        );
    }

    #[Test]
    public function readsNothingForNoChanges(): void
    {
        $sections = new RecordingSections();

        static::assertSame([[], []], [(new CurrentValues($sections))->of([]), $sections->asked]);
    }
}

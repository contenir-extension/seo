<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Report;

use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\TitleTaken;
use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;
use Contenir\Extension\Seo\Section\Hidden;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;

#[Group('unit')]
final class SectionIssuesTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    /**
     * @return array<string, array{list<MetaIssue>, bool}>
     */
    public static function issuesProvider(): array
    {
        $short = new MetaIssue('description', 'description', 'The description is 27 characters.');

        return [
            'a missing title'             => [[new MetaIssue('title', 'title', 'Add a title.', missing: true)], true],
            'a missing description'       => [
                [new MetaIssue('description', 'description', 'Add a description.', missing: true)],
                true,
            ],
            'a title another record has'  => [[new MetaIssue('title', 'title', TitleTaken::MESSAGE)], true],
            'a title too long'            => [[new MetaIssue('title', 'title', 'The title is 65 characters.')], true],
            'no share image'              => [[new MetaIssue('image', 'image', 'Add an image.', missing: true)], true],
            'a description of any length' => [[$short], false],
            'a small share image'         => [[new MetaIssue('image', '', 'The share image is 600×315.')], false],
            'hidden from search'          => [[new MetaIssue('discoverable', 'visible', Hidden::BY_SWITCH)], false],
            'advice beside a real gap'    => [
                [$short, new MetaIssue('title', 'title', 'Add a title.', missing: true)],
                true,
            ],
        ];
    }

    /**
     * @param list<MetaIssue> $issues
     */
    private static function record(int $id, array $issues): RecordIssues
    {
        return new RecordIssues(new RecordReference(self::SECTION, $id), "House {$id}", $issues);
    }

    /**
     * @param list<MetaIssue> $issues
     */
    #[Test]
    #[DataProvider('issuesProvider')]
    public function aRecordNeedsAttentionForAHighOrMediumIssue(array $issues, bool $attention): void
    {
        static::assertSame($attention, self::record(1, $issues)->needsAttention());
    }

    #[Test]
    public function keepsOnlyTheRecordsThatNeedAttentionEachOnce(): void
    {
        $section = new SectionIssues(
            self::SECTION,
            'Projects',
            4,
            [
                self::record(1, [new MetaIssue('description', 'description', 'The description is 27 characters.')]),
                self::record(2, [
                    new MetaIssue('title', 'title', 'Add a title.', missing: true),
                    new MetaIssue('description', 'description', 'Add a description.', missing: true),
                ]),
                self::record(3, [new MetaIssue('discoverable', 'visible', Hidden::BY_SWITCH)]),
                self::record(4, [new MetaIssue('title', 'title', 'The title is 65 characters.')]),
            ],
            0,
        );

        static::assertSame(
            [2, 4],
            array_map(static fn(RecordIssues $record): int|string => $record->record->id, $section->attention()),
        );
    }
}

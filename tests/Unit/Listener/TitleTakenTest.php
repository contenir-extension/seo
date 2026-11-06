<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Listener\TitleTaken;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\PagedSections;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function array_slice;
use function range;

#[Group('unit')]
final class TitleTakenTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string NOTE = 'Another record in this section has the same title, so search results can\'t tell them apart.';

    /**
     * @return array<string, array{string, string}>
     */
    public static function caseProvider(): array
    {
        return [
            'the other in capitals' => [" CAFÉ \n", 'café'],
            'this one in capitals'  => [' café ', ' CAFÉ '],
        ];
    }

    /**
     * @return array<string, array{int, ?string}>
     */
    public static function positionProvider(): array
    {
        return [
            'on the first page'          => [3, self::NOTE],
            'on the last page'           => [(TitleTaken::PAGE * 2) + 3, self::NOTE],
            'at the end of a full page'  => [TitleTaken::PAGE - 1, self::NOTE],
            'past the most records read' => [TitleTaken::MAX_ROWS, null],
        ];
    }

    /**
     * @return list<RecordRow>
     */
    private static function rows(int $count, int $duplicate): array
    {
        return array_map(
            static fn(int $index): RecordRow => new RecordRow(
                new RecordReference(self::SECTION, $index + 10),
                RecordStatus::Published,
                ['meta_title' => '', 'title' => $index === $duplicate ? ' HOUSE ' : "Project {$index}"],
            ),
            range(0, $count - 1),
        );
    }

    #[Test]
    public function aRecordWithNothingKeptGoesByItsColumnsNotItsAdvancedColumnsText(): void
    {
        $rows = [
            new RecordRow(new RecordReference(self::SECTION, 2), RecordStatus::Draft, [
                'seo'   => null,
                'title' => 'House',
            ]),
            new RecordRow(new RecordReference(self::SECTION, 3), RecordStatus::Draft, [
                'seo'   => '{"robots":"noindex"}',
                'title' => '',
            ]),
        ];

        static::assertSame(self::NOTE, $this->taken($rows)->note(self::SECTION, ['seo', 'title'], 'house', 1, 'seo'));
        static::assertNull($this->taken($rows)->note(
            self::SECTION,
            ['seo', 'title'],
            '{"robots":"noindex"}',
            1,
            'seo',
        ));
    }

    #[Test]
    public function aRecordWithoutATitleIsNoDuplicate(): void
    {
        $rows = [new RecordRow(new RecordReference(self::SECTION, 2), RecordStatus::Draft, ['title' => null])];

        static::assertNull($this->taken($rows)->note(self::SECTION, ['title'], 'House', 1));
    }

    #[Test]
    public function aSectionWithoutTitleColumnsIsNotRead(): void
    {
        static::assertNull($this->taken([])->note(self::SECTION, [], 'House', 1));
    }

    #[Test]
    public function aShortSectionIsReadOnce(): void
    {
        $sections = $this->createMock(SectionsInterface::class);
        $sections->expects(static::once())->method('rows')->willReturn(self::rows(5, duplicate: -1));

        static::assertNull((new TitleTaken($sections))->note(self::SECTION, ['title'], 'House', 1));
    }

    #[Test]
    public function aTitleInTheColumnsIsPassedOverWhenTheAdvancedColumnKeepsAnother(): void
    {
        $rows = [
            new RecordRow(new RecordReference(self::SECTION, 2), RecordStatus::Draft, [
                'seo'   => '{"title":"Other"}',
                'title' => 'House',
            ]),
        ];

        static::assertNull($this->taken($rows)->note(self::SECTION, ['seo', 'title'], 'house', 1, 'seo'));
    }

    #[Test]
    public function comparesATitleKeptInTheAdvancedColumnOverTheRecordsColumns(): void
    {
        $rows = [
            new RecordRow(new RecordReference(self::SECTION, 2), RecordStatus::Draft, [
                'seo'   => '{"title":" CAFÉ "}',
                'title' => 'Other',
            ]),
        ];

        static::assertSame(self::NOTE, $this->taken($rows)->note(self::SECTION, ['seo', 'title'], 'café', 1, 'seo'));
    }

    #[Test]
    #[DataProvider('caseProvider')]
    public function comparesTitlesWithoutCaseOrSpace(string $other, string $title): void
    {
        $rows = [new RecordRow(new RecordReference(self::SECTION, 2), RecordStatus::Draft, ['title' => $other])];

        static::assertSame(self::NOTE, $this->taken($rows)->note(self::SECTION, ['title'], $title, 1));
    }

    #[Test]
    #[DataProvider('positionProvider')]
    public function findsAnotherRecordWithTheTitleAPageAtATime(int $duplicate, ?string $note): void
    {
        static::assertSame($note, $this->taken(self::rows(TitleTaken::MAX_ROWS + 50, $duplicate))->note(
            self::SECTION,
            ['meta_title', 'title'],
            'House',
            1,
        ));
    }

    #[Test]
    public function findsATitleOnALaterPageOfRecords(): void
    {
        $sections = new PagedSections(total: 250);

        static::assertSame(
            self::NOTE,
            (new TitleTaken($sections))->note(self::SECTION, ['title'], 'house 250', 9999),
        );
    }

    #[Test]
    public function readsNoMoreThanTheMostRecordsLookingForATitle(): void
    {
        $sections = new PagedSections(total: 6000);

        static::assertSame(
            [null, 50],
            [(new TitleTaken($sections))->note(self::SECTION, ['title'], 'No such house', 9999), $sections->calls],
        );
    }

    #[Test]
    public function theRecordItselfIsNoDuplicate(): void
    {
        static::assertNull($this->taken(self::rows(5, duplicate: 2))->note(self::SECTION, ['title'], 'House', '12'));
    }

    /**
     * @param list<RecordRow> $rows
     */
    private function taken(array $rows): TitleTaken
    {
        $calls    = 0;
        $sections = $this->createStub(SectionsInterface::class);
        $sections->method('rows')
            ->willReturnCallback(
                static function (
                    string $section,
                    array $columns,
                    array $ids = [],
                    int $limit = 100,
                    int $offset = 0,
                ) use ($rows, &$calls): array {
                    if (++$calls > 80) {
                        throw new LogicException('The sections were asked for too many pages');
                    }

                    return array_slice($rows, $offset, $limit);
                },
            );

        return new TitleTaken($sections);
    }
}

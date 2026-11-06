<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\DescriptionChange;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;
use function str_repeat;

#[Group('unit')]
#[Group('bulk')]
final class DescriptionChangeTest extends TestCase
{
    /**
     * @param list<string> $extra
     * @param list<string> $fields
     */
    private static function block(
        array $extra = [],
        array $fields = ['title', 'meta_description', 'description', 'summary'],
    ): SectionBlock {
        return new SectionBlock('library.projects.project', new Element('seo'), [...$fields, ...$extra]);
    }

    private static function content(int $length, string $character = 'a'): string
    {
        return (string) json_encode([
            ['type' => 'text', 'status' => '1', 'fields' => ['description' => str_repeat($character, $length)]],
        ]);
    }

    /**
     * @param array<string, string|int|null> $values
     */
    private static function row(array $values): RecordRow
    {
        return new RecordRow(new RecordReference('library.projects.project', 1), RecordStatus::Published, $values);
    }

    private static function seo(?string $advanced = null): SeoBlock
    {
        return new SeoBlock(
            'project',
            roles: ['description' => ['meta_description', 'description']],
            advanced: $advanced,
        );
    }

    #[Test]
    public function countsASectionDescriptionInCharactersNotBytes(): void
    {
        static::assertNull(DescriptionChange::of(
            self::block(),
            self::seo(),
            self::row(['section' => self::content(
                length: 30,
                character: 'é',
            )]),
        ));
    }

    #[Test]
    public function countsASummaryInCharactersNotBytes(): void
    {
        static::assertNull(DescriptionChange::of(
            self::block(),
            self::seo(),
            self::row(['summary' => str_repeat(
                string: 'é',
                times: 30,
            )]),
        ));
    }

    #[Test]
    public function leavesOutARecordThatAlreadyHasADescription(): void
    {
        $row = self::row([
            'meta_description' => 'Own.',
            'summary'          => str_repeat(
                string: 'a',
                times: 60,
            ),
        ]);

        static::assertNull(DescriptionChange::of(self::block(), self::seo(), $row));
    }

    #[Test]
    public function leavesOutASectionDescriptionOneCharacterShort(): void
    {
        static::assertNull(DescriptionChange::of(
            self::block(),
            self::seo(),
            self::row(['section' => self::content(length: 49)]),
        ));
    }

    #[Test]
    public function leavesOutASectionWithNowhereToWriteOne(): void
    {
        $row = self::row(['summary' => str_repeat(
            string: 'a',
            times: 60,
        )]);

        static::assertNull(DescriptionChange::of(self::block([], ['title']), self::seo(), $row));
    }

    #[Test]
    public function leavesOutASummaryOneCharacterShort(): void
    {
        static::assertNull(DescriptionChange::of(
            self::block(),
            self::seo(),
            self::row(['summary' => str_repeat(
                string: 'a',
                times: 49,
            )]),
        ));
    }

    #[Test]
    public function targetsNothingWhenTheSectionHasNeither(): void
    {
        static::assertSame(
            [null, null],
            DescriptionChange::target(self::block([], ['title']), self::seo(advanced: 'seo')),
        );
    }

    #[Test]
    public function targetsTheAdvancedColumnsDescriptionKeyWhenTheSectionHasTheColumn(): void
    {
        static::assertSame(
            ['seo', 'description'],
            DescriptionChange::target(self::block(['seo']), self::seo(advanced: 'seo')),
        );
    }

    #[Test]
    public function targetsTheFirstDescriptionColumnTheSectionHas(): void
    {
        static::assertSame(
            ['description', null],
            DescriptionChange::target(self::block([], ['title', 'description']), self::seo()),
        );
    }

    #[Test]
    public function writesADescriptionFromTheSectionFieldOfExactlyTheShortestLength(): void
    {
        $change = DescriptionChange::of(
            self::block(),
            self::seo(),
            self::row(['section' => self::content(length: 50)]),
        );

        static::assertSame(
            [
                'meta_description',
                null,
                str_repeat(
                    string: 'a',
                    times: 50,
                ),
                null,
            ],
            $change,
        );
    }

    #[Test]
    public function writesASummaryOfExactlyTheShortestLengthThatDescribesAPage(): void
    {
        $change = DescriptionChange::of(
            self::block(),
            self::seo(),
            self::row(['summary' => str_repeat(
                string: 'a',
                times: 50,
            )]),
        );

        static::assertSame(
            [
                'meta_description',
                null,
                str_repeat(
                    string: 'a',
                    times: 50,
                ),
                null,
            ],
            $change,
        );
    }

    #[Test]
    public function writesTheSectionDescriptionUnderTheAdvancedColumnsDescriptionKey(): void
    {
        $change = DescriptionChange::of(
            self::block(['seo']),
            self::seo(advanced: 'seo'),
            self::row(['section' => self::content(length: 60)]),
        );

        static::assertSame(
            [
                'seo',
                null,
                str_repeat(
                    string: 'a',
                    times: 60,
                ),
                'description',
            ],
            $change,
        );
    }

    #[Test]
    public function writesTheSummaryUnderTheAdvancedColumnsDescriptionKey(): void
    {
        $change = DescriptionChange::of(
            self::block(['seo']),
            self::seo(advanced: 'seo'),
            self::row(['summary' => str_repeat(
                string: 'a',
                times: 60,
            )]),
        );

        static::assertSame(
            [
                'seo',
                null,
                str_repeat(
                    string: 'a',
                    times: 60,
                ),
                'description',
            ],
            $change,
        );
    }
}

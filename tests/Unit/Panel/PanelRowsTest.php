<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Panel;

use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\PanelRow;
use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\TitleTaken;
use Contenir\Extension\Seo\Panel\PanelFacts;
use Contenir\Extension\Seo\Panel\PanelRows;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

#[Group('unit')]
final class PanelRowsTest extends TestCase
{
    /**
     * What the panel says of a record the site rendered, in a section that keeps a share image.
     */
    private static function facts(Inspection $site): PanelFacts
    {
        return PanelFacts::ofSite($site, new SeoBlock('project', roles: ['image' => ['image']]));
    }

    #[Test]
    public function countsADescriptionInCharactersNotBytes(): void
    {
        $facts = self::facts(
            new Inspection(
                's',
                1,
                'House',
                str_repeat(
                    string: 'é',
                    times: 40,
                ),
                'https://x/a.jpg',
            ),
        );

        static::assertSame(
            [['Description', 'Short (40 characters)']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('description', '', 'Short.')], null)),
        );
    }

    #[Test]
    public function countsATitleInCharactersNotBytes(): void
    {
        $facts = self::facts(
            new Inspection(
                's',
                1,
                str_repeat(
                    string: 'é',
                    times: 65,
                ),
                'A description.',
                'https://x/a.jpg',
            ),
        );

        static::assertSame(
            [['Title', 'Too long (65 of about 60)']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('title', 'title', 'Too long.')], null)),
        );
    }

    #[Test]
    public function givesEveryRowTheFixLinkItWasGiven(): void
    {
        $fix   = new Link('Fix', '/admin/records/4');
        $facts = self::facts(new Inspection('s', 1, 'House', 'A description.', 'https://x/a.jpg'));

        static::assertSame(
            [$fix],
            array_map(
                static fn(PanelRow $row): ?Link => $row->fix,
                PanelRows::of($facts, [new MetaIssue('image', '', 'Soft.')], $fix),
            ),
        );
    }

    #[Test]
    public function saysADescriptionOfExactlyTheShortestGoodLengthIsTooLong(): void
    {
        $facts = self::facts(
            new Inspection(
                's',
                1,
                'House',
                str_repeat(
                    string: 'a',
                    times: 70,
                ),
                'https://x/a.jpg',
            ),
        );

        static::assertSame(
            [['Description', 'Too long (70 of about 160)']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('description', '', 'Long.')], null)),
        );
    }

    #[Test]
    public function saysAShortDescriptionByItsLength(): void
    {
        $facts = self::facts(new Inspection('s', 1, 'House', str_repeat('a', times: 40), 'https://x/a.jpg'));

        static::assertSame(
            [['Description', 'Short (40 characters)']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('description', '', 'Short.')], null)),
        );
    }

    #[Test]
    public function saysASmallShareImageWithItsSizeWhenTheSiteSaidIt(): void
    {
        $facts = self::facts(new Inspection('s', 1, 'House', 'A description.', 'https://x/a.jpg', 600, 315));

        static::assertSame(
            [['Share image', 'Small (600×315)']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('image', '', 'Soft.')], null)),
        );
    }

    #[Test]
    public function saysASmallShareImageWithoutItsSizeWhenTheSiteDidNot(): void
    {
        $facts = self::facts(new Inspection('s', 1, 'House', 'A description.', 'https://x/a.jpg'));

        static::assertSame(
            [['Share image', 'Small']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('image', '', 'Soft.')], null)),
        );
    }

    #[Test]
    public function saysATitleAnotherRecordHas(): void
    {
        $facts = self::facts(new Inspection('s', 1, 'House', 'A description.', 'https://x/a.jpg'));

        static::assertSame(
            [['Title', 'Another record has it']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('title', 'title', TitleTaken::MESSAGE)], null)),
        );
    }

    #[Test]
    public function saysATitleTooLongByItsLength(): void
    {
        $facts = self::facts(
            new Inspection('s', 1, str_repeat('t', times: 72), 'A description.', 'https://x/a.jpg'),
        );

        static::assertSame(
            [['Title', 'Too long (72 of about 60)']],
            $this->shown(PanelRows::of($facts, [new MetaIssue('title', 'title', 'Cut off.')], null)),
        );
    }

    /**
     * @param list<PanelRow> $rows
     *
     * @return list<array{string, string}>
     */
    private function shown(array $rows): array
    {
        return array_map(static fn(PanelRow $row): array => [$row->label, $row->detail], $rows);
    }
}

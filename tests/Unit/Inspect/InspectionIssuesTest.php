<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Inspect;

use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Report\InspectionIssues;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function str_repeat;

#[Group('unit')]
final class InspectionIssuesTest extends TestCase
{
    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    /**
     * @return array<string, array{string|null, string|null, string|null, list<array{string, string, bool}>}>
     */
    public static function headProvider(): array
    {
        return [
            'nothing to fix'      => ['House | Site', self::DESCRIPTION, '/a.jpg', []],
            'no title'            => [
                null,
                self::DESCRIPTION,
                '/a.jpg',
                [['title', 'Add a title for search results.', true]],
            ],
            'a title too long'    => [
                str_repeat('a', times: 61),
                self::DESCRIPTION,
                '/a.jpg',
                [[
                    'title',
                    'With the site name, the title is 61 characters; search results cut it off after about 60.',
                    false,
                ]],
            ],
            'no description'      => [
                'House | Site',
                null,
                '/a.jpg',
                [[
                    'description',
                    'Add a description for search results and shared links.',
                    true,
                ]],
            ],
            'a short description' => [
                'House | Site',
                'A house.',
                '/a.jpg',
                [[
                    'description',
                    'The description is 8 characters; search results have room for about 160.',
                    false,
                ]],
            ],
            'no image'            => [
                'House | Site',
                self::DESCRIPTION,
                null,
                [['image', 'Add an image for shared links.', true]],
            ],
        ];
    }

    /**
     * @return array<string, array{int|null, int|null, list<string>}>
     */
    public static function sizeProvider(): array
    {
        $small = static fn(
            int $w,
            int $h,
        ): string => "The share image is {$w}×{$h}, and sharing sites show about 1200×630, so it may look soft.";

        return [
            'exactly enough' => [1200, 630, []],
            'larger'         => [2000, 1333, []],
            'too narrow'     => [1199, 630, [$small(1199, h: 630)]],
            'too short'      => [1200, 629, [$small(1200, h: 629)]],
            'width unknown'  => [null, 630, []],
            'height unknown' => [1200, null, []],
        ];
    }

    /**
     * @param list<array{string, string, bool}> $issues
     */
    #[Test]
    #[DataProvider('headProvider')]
    public function goesByWhatTheSiteRendered(?string $title, ?string $description, ?string $image, array $issues): void
    {
        static::assertSame($issues, array_map(
            static fn(MetaIssue $issue): array => [$issue->role, $issue->message, $issue->missing],
            InspectionIssues::of(new Inspection('s', 0, $title, $description, $image)),
        ));
    }

    /**
     * @param list<string> $issues
     */
    #[Test]
    #[DataProvider('sizeProvider')]
    public function warnsAboutASmallImage(?int $width, ?int $height, array $issues): void
    {
        static::assertSame($issues, array_map(
            static fn(MetaIssue $issue): string => $issue->message,
            InspectionIssues::of(new Inspection('s', 0, 'House | Site', self::DESCRIPTION, '/a.jpg', $width, $height)),
        ));
    }
}

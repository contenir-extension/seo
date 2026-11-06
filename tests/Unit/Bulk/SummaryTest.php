<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Seo\Bulk\Summary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function rtrim;
use function str_repeat;

#[Group('unit')]
final class SummaryTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function summaryProvider(): array
    {
        $long = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen. The deck runs the width of the house and looks over the old lemon tree and the vegetable beds beyond.';

        return [
            'nothing to say'               => ['', null],
            'only markup'                  => ['<p> </p><br>', null],
            'short text is kept as it is'  => ['A small hut by the sea.', 'A small hut by the sea.'],
            'markup and entities go'       => ['<p>Tea &amp; <strong>cake</strong></p>', 'Tea & cake'],
            'paragraphs are kept apart'    => ['<p>One.</p><p>Two.</p>', 'One. Two.'],
            'runs of space become one'     => ["A  hut\n\nby   the sea.", 'A hut by the sea.'],
            'the first sentences that fit' => [
                $long,
                'A weatherboard house in Northcote, opened up to its garden with a new kitchen.',
            ],
            'words that fit, then a stop'  => [
                str_repeat(
                    string: 'word ',
                    times: 60,
                ),
                rtrim(str_repeat(
                    string: 'word ',
                    times: 31,
                )) . '…',
            ],
        ];
    }

    #[Test]
    #[DataProvider('summaryProvider')]
    public function writesADescriptionFromTheText(string $text, ?string $expected): void
    {
        static::assertSame($expected, Summary::of($text));
    }
}

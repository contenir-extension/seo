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
            'nothing to say'                                   => ['', null],
            'only markup'                                      => ['<p> </p><br>', null],
            'short text is kept as it is'                      => [
                'A small hut by the sea.',
                'A small hut by the sea.',
            ],
            'markup and entities go'                           => [
                '<p>Tea &amp; <strong>cake</strong></p>',
                'Tea & cake',
            ],
            'paragraphs are kept apart'                        => ['<p>One.</p><p>Two.</p>', 'One. Two.'],
            'runs of space become one'                         => ["A  hut\n\nby   the sea.", 'A hut by the sea.'],
            'text exactly as long as it may be'                => [
                str_repeat(
                    string: 'a',
                    times: 155,
                ),
                str_repeat(
                    string: 'a',
                    times: 155,
                ),
            ],
            'accented text counted in characters'              => [
                str_repeat(
                    string: 'é',
                    times: 100,
                ),
                str_repeat(
                    string: 'é',
                    times: 100,
                ),
            ],
            'quotes and apostrophes decoded'                   => [
                'Say &quot;hi&quot; &amp; it&#039;s &apos;ok&apos;',
                'Say "hi" & it\'s \'ok\'',
            ],
            'a short sentence in accented text is no sentence' => [
                str_repeat(
                    string: 'é',
                    times: 25,
                )
                    . '. '
                    . str_repeat(
                        string: 'è',
                        times: 160,
                    ),
                str_repeat(
                    string: 'é',
                    times: 25,
                ) . '.…',
            ],
            'words cut at the last space in accented text'     => [
                str_repeat(
                    string: 'éé ',
                    times: 70,
                ),
                str_repeat(
                    string: 'éé ',
                    times: 50,
                ) . 'éé…',
            ],
            'a comma before the cut is dropped'                => [
                str_repeat(
                    string: 'abcd, ',
                    times: 40,
                ),
                str_repeat(
                    string: 'abcd, ',
                    times: 24,
                ) . 'abcd…',
            ],
            'the first sentences that fit'                     => [
                $long,
                'A weatherboard house in Northcote, opened up to its garden with a new kitchen.',
            ],
            'words that fit, then a stop'                      => [
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

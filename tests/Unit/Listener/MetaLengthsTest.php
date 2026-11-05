<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Seo\Listener\MetaLengths;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

#[Group('unit')]
final class MetaLengthsTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function descriptionProvider(): array
    {
        return [
            'too short'        => [
                str_repeat('é', times: 69),
                'The description is 69 characters; search results have room for about 160.',
            ],
            'just long enough' => [str_repeat('é', times: 70), null],
            'as long as shown' => [str_repeat('é', times: 160), null],
            'too long'         => [
                str_repeat('é', times: 161),
                'The description is 161 characters; search results cut it off after about 160.',
            ],
        ];
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function titleProvider(): array
    {
        return [
            'as long as search results show' => [str_repeat('é', times: 60), null],
            'one character too long'         => [
                str_repeat('é', times: 61),
                'With the site name, the title is 61 characters; search results cut it off after about 60.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('descriptionProvider')]
    public function aDescriptionIsNotedOutsideTheLengthSearchResultsShow(string $description, ?string $note): void
    {
        static::assertSame($note, MetaLengths::description($description));
    }

    #[Test]
    #[DataProvider('titleProvider')]
    public function aTitleIsNotedWhenSearchResultsWouldCutItOff(string $shown, ?string $note): void
    {
        static::assertSame($note, MetaLengths::title($shown));
    }
}

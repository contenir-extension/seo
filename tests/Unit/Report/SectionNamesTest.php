<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Report;

use Contenir\Extension\Seo\Report\SectionNames;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SectionNamesTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function pluralProvider(): array
    {
        return [
            'a plain name'             => ['Page', 'Pages'],
            'a name of several words'  => ['News article', 'News articles'],
            'a name ending in y'       => ['Gallery', 'Galleries'],
            'a capital y'              => ['PARTY', 'PARTies'],
            'a y in the middle'        => ['Cyber', 'Cybers'],
            'a vowel before the y'     => ['Survey', 'Surveys'],
            'a name already plural'    => ['Projects', 'Projects'],
            'a plural in capitals'     => ['NEWS', 'NEWS'],
            'a name with spaces round' => [' Event ', 'Events'],
        ];
    }

    #[Test]
    #[DataProvider('pluralProvider')]
    public function namesTheRecordsOfASectionInThePlural(string $name, string $expected): void
    {
        static::assertSame($expected, SectionNames::plural($name));
    }
}

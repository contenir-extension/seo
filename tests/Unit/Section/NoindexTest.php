<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Seo\Section\Noindex;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class NoindexTest extends TestCase
{
    /**
     * @return array<string, array{?string, ?string, string, bool}>
     */
    public static function pageProvider(): array
    {
        return [
            'nothing said'                           => [null, null, '', false],
            'a tag of its own'                       => ['noindex, follow', null, 'index, follow', true],
            'a tag that repeats the site\'s setting' => ['noindex, follow', null, 'noindex, follow', false],
            'a tag that lets it in'                  => ['index, follow', null, 'index, follow', false],
            'a header of its own'                    => [null, 'noindex, follow', '', true],
            'a header in capitals'                   => [null, ' NOINDEX ', '', true],
            'the header of a hidden copy'            => [null, 'noindex, nofollow', '', false],
            'a hidden copy\'s header in capitals'    => [null, ' Noindex, Nofollow ', '', false],
            'a hidden copy with a tag of its own'    => ['noindex', 'noindex, nofollow', 'index, follow', true],
            'a header that only stops links'         => [null, 'nofollow', '', false],
        ];
    }

    /**
     * @return array<string, array{?string, bool}>
     */
    public static function robotsProvider(): array
    {
        return [
            'nothing'                     => [null, false],
            'an empty value'              => ['', false],
            'noindex'                     => ['noindex', true],
            'noindex among others'        => ['max-snippet:20, noindex, follow', true],
            'none'                        => ['none', true],
            'in capitals with space'      => ['  NoIndex ,FOLLOW', true],
            'addressed to one crawler'    => ['googlebot: noindex', true],
            'to a crawler, with no space' => ['googlebot:noindex', true],
            'index'                       => ['index, follow', false],
            'only links unfollowed'       => ['nofollow', false],
            'a word that holds noindex'   => ['noindexing', false],
            'a date after a colon'        => ['unavailable_after: 25 Jun 2030 15:00:00 PST', false],
        ];
    }

    #[Test]
    #[DataProvider('pageProvider')]
    public function countsOnlyWhatThePageItselfSends(?string $tag, ?string $header, string $site, bool $out): void
    {
        static::assertSame($out, Noindex::onPage($tag, $header, $site));
    }

    #[Test]
    #[DataProvider('robotsProvider')]
    public function readsNoindexAndNoneAsKeepingAPageOut(?string $robots, bool $out): void
    {
        static::assertSame($out, Noindex::in($robots));
    }
}

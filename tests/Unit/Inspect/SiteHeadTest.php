<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Inspect;

use Contenir\Extension\Seo\Inspect\SiteHead;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('unit')]
final class SiteHeadTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function refusedProvider(): array
    {
        return [
            'not JSON'            => ['<html>'],
            'not an object'       => ['"Home"'],
            'no status'           => ['{"title":"Home"}'],
            'a status not a code' => ['{"status":"200"}'],
            'a page that failed'  => ['{"status":500,"title":"Oops"}'],
            'a page not found'    => ['{"status":404,"title":"Missing"}'],
        ];
    }

    #[Test]
    #[DataProvider('refusedProvider')]
    public function anAnswerWithoutAPagesHeadHoldsNone(string $json): void
    {
        static::assertNull(SiteHead::fromJson($json));
    }

    #[Test]
    public function readsTheAnswer(): void
    {
        static::assertEquals(
            new SiteHead('Home | Site', 'About us.', '/a.jpg', 'A', 'https://x.test/', 'index', 'noindex', [[
                '@type' => 'WebPage',
            ]]),
            SiteHead::fromJson((string) json_encode([
                'status'       => 200,
                'title'        => 'Home | Site',
                'description'  => 'About us.',
                'image'        => '/a.jpg',
                'imageAlt'     => 'A',
                'canonical'    => 'https://x.test/',
                'robots'       => 'index',
                'robotsHeader' => 'noindex',
                'jsonLd'       => [['@type' => 'WebPage'], 'not a node'],
            ])),
        );
    }

    #[Test]
    public function whatThePageLeavesOutIsNothing(): void
    {
        static::assertEquals(
            new SiteHead(null, null, null, null, null, null, null, []),
            SiteHead::fromJson('{"status":200,"title":"","description":null,"image":3,"jsonLd":"x"}'),
        );
    }
}

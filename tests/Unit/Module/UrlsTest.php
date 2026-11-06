<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Seo\Module\Urls;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class UrlsTest extends TestCase
{
    #[Test]
    public function addressesAHandlerOfTheModule(): void
    {
        static::assertSame('/admin/extension/seo/records', Urls::to('records'));
    }

    #[Test]
    public function appendsTheQueryEncoded(): void
    {
        static::assertSame(
            '/admin/extension/seo/records?run=a+b&page=2',
            Urls::to('records', ['run' => 'a b', 'page' => 2]),
        );
    }
}

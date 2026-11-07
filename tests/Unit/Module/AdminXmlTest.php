<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

use function dirname;
use function simplexml_load_file;

#[Group('unit')]
final class AdminXmlTest extends TestCase
{
    #[Test]
    public function givesTheStrategyScreenAndItsSaveASectionEachSoTheyAreGrantedOnTheirOwn(): void
    {
        $sections = $this->area()->category->strategy->section;

        static::assertSame(
            ['strategy' => 'strategy', 'save' => 'strategy-save'],
            ['strategy' => (string) $sections->screen->handler, 'save' => (string) $sections->save->handler],
        );
    }

    #[Test]
    public function keepsTheOverviewAsTheAreasOwnScreen(): void
    {
        static::assertSame('overview', (string) $this->area()->handler);
    }

    #[Test]
    public function keepsThoseSectionsOutOfTheMenu(): void
    {
        $sections = $this->area()->category->strategy->section;

        static::assertSame(['1', '1'], [(string) $sections->screen->hidden, (string) $sections->save->hidden]);
    }

    private function area(): SimpleXMLElement
    {
        $admin = simplexml_load_file(dirname(__DIR__, levels: 3) . '/config/admin.xml');
        static::assertInstanceOf(SimpleXMLElement::class, $admin);

        return $admin->seo;
    }
}

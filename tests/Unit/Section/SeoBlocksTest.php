<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Tests\TestAsset\Sections\OverridableSections;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SeoBlocksTest extends TestCase
{
    private const string PAGES = 'site.page.home';

    #[Test]
    public function givesEachBlockAsTheSiteHasItWithAStrategySavedEarlierInItsPlace(): void
    {
        $blocks = SeoBlocks::of($this->context('{"site.page.home": "<seo type=\"page\" schema=\"AboutPage\"/>"}'));

        static::assertSame('AboutPage', $blocks->all()[0]->element->attribute('schema'));
    }

    #[Test]
    public function givesOneSectionsBlockWithAStrategySavedEarlierInItsPlace(): void
    {
        $blocks = SeoBlocks::of($this->context('{"site.page.home": "<seo type=\"page\" schema=\"AboutPage\"/>"}'));

        static::assertSame('AboutPage', $blocks->one(self::PAGES)?->element->attribute('schema'));
    }

    #[Test]
    public function givesTheBlocksAsAdminXmlSaysWhereNoStrategyWasSaved(): void
    {
        $blocks = SeoBlocks::of($this->context(''));

        static::assertSame(
            ['WebPage', 'WebPage', null],
            [
                $blocks->all()[0]->element->attribute('schema'),
                $blocks->one(self::PAGES)?->element->attribute('schema'),
                $blocks->one('site.news.article'),
            ],
        );
    }

    private function context(string $saved): ExtensionContextInterface
    {
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')
            ->willReturn(new OverridableSections([
                self::PAGES => new SectionBlock(self::PAGES, Element::parse('<seo type="page" schema="WebPage"/>')),
            ]));
        $context->method('settings')
            ->willReturn(
                new ArraySettings(StrategySection::define(), [StrategySection::SECTIONS => $saved]),
            );

        return $context;
    }
}

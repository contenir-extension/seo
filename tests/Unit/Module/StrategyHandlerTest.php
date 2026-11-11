<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Module\StrategyHandler;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Tests\TestAsset\Sections\OverridableSections;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[Group('unit')]
final class StrategyHandlerTest extends TestCase
{
    private const string PAGES = 'site.page.home';

    private const string NEWS = 'site.news.article';

    #[Test]
    public function listsTheSectionsChangedHereAndTheVersionTheyWereReadAt(): void
    {
        $sections = new OverridableSections([
            self::PAGES => new SectionBlock(self::PAGES, Element::parse('<seo type="page"/>'), ['title']),
            self::NEWS  => new SectionBlock(self::NEWS, Element::parse('<seo type="news"/>'), ['title']),
        ]);
        $sections->override(self::NEWS, Element::parse('<seo type="news" schema="Article"/>'), '');
        $records = $this->createStub(RecordsInterface::class);
        $records->method('sections')->willReturn([]);
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturn($records);
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')->willReturn($sections);
        $context->method('services')->willReturn($services);
        $context->method('settings')->willReturn(new ArraySettings(StrategySection::define()));

        $values = (new StrategyHandler())->handle(
            (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/strategy'),
            $context,
        )
            ->template
            ->values ?? [];

        static::assertSame([[self::NEWS], '1'], [$values['customised'] ?? null, $values['version'] ?? null]);
    }
}

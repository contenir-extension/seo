<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Check\SectionsCheck;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Tests\TestAsset\Sections\OverridableSections;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SectionsCheckTest extends TestCase
{
    private const string PAGES = 'site.page.home';

    #[Test]
    public function asksForNoRecordsToFindWhetherASectionIsLent(): void
    {
        $sections = $this->sections(null);

        (new SectionsCheck())->check($this->context(null, $sections));

        static::assertSame([0], $sections->limits);
    }

    #[Test]
    public function namesASectionTheSiteWillNotLendAndWhy(): void
    {
        static::assertEquals(
            CheckResult::warning(
                'Check the <seo> blocks in admin.xml: site.page.home can\'t be read, so its records are not reported: The block on site.page.home does not read column "images".',
            ),
            (new SectionsCheck())->check($this->context(RowsRefusedException::column(self::PAGES, 'images'))),
        );
    }

    #[Test]
    public function passesASectionTheSiteLends(): void
    {
        static::assertEquals(
            CheckResult::success('1 section is described for search.'),
            (new SectionsCheck())->check($this->context(null)),
        );
    }

    private function context(
        ?RowsRefusedException $unread,
        ?OverridableSections $sections = null,
    ): ExtensionContextInterface {
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')->willReturn($sections ?? $this->sections($unread));
        $context->method('settings')->willReturn(new ArraySettings(StrategySection::define()));

        return $context;
    }

    private function sections(?RowsRefusedException $unread): OverridableSections
    {
        return new OverridableSections(
            [self::PAGES => new SectionBlock(self::PAGES, Element::parse('<seo type="page"/>'), ['title'], true)],
            unread: $unread,
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Event\Change;
use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Seo\Listener\CheckMeta;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Tests\TestAsset\Sections\OverridableSections;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\SettingsContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class CheckMetaTest extends TestCase
{
    private const string PAGES = 'site.page.home';

    #[Test]
    public function letsASaveGoAheadWhereTheSiteWillNotLendTheOtherRecordsTitles(): void
    {
        $event = new RecordPreSave(self::PAGES, 2, new Change([], ['title' => 'House']));

        (new CheckMeta())->handle($event, $this->context(RowsRefusedException::column(self::PAGES, 'title')));

        static::assertSame([true, []], [$event->proceeds(), $event->warnings()['title'] ?? []]);
    }

    private function context(RowsRefusedException $unread): ExtensionContextInterface
    {
        $settings = new SettingsContext();
        $context  = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')
            ->willReturn(new OverridableSections(
                [
                    self::PAGES => new SectionBlock(
                        self::PAGES,
                        Element::parse('<seo type="page"><title>title</title></seo>'),
                        ['title'],
                        true,
                    ),
                ],
                unread: $unread,
            ));
        $context->method('settings')
            ->willReturnCallback(
                static fn(?string $section = null): SettingsInterface => StrategySection::NAME === $section
                    ? new ArraySettings(StrategySection::define())
                    : $settings->settings($section),
            );

        return $context;
    }
}

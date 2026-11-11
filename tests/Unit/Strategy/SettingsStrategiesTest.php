<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Strategy;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Testing\MemoryLogger;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Strategy\SettingsStrategies;
use Contenir\Extension\Seo\Tests\TestAsset\Sections\OverridableSections;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function count;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;

#[Group('unit')]
final class SettingsStrategiesTest extends TestCase
{
    private const string PAGES = 'site.page.home';

    private const string NEWS = 'site.news.article';

    private const string SAVED = '<seo type="page"><image alt="title">images, section</image></seo>';

    private MemoryLogger $logger;

    /**
     * @return array<string, array{mixed}>
     */
    public static function nothingProvider(): array
    {
        return [
            'nothing'            => [''],
            'not text'           => [null],
            'not JSON'           => ['{'],
            'a list'             => ['["<seo/>"]'],
            'blocks not as text' => ['{"site.page.home": {"type": "page"}}'],
        ];
    }

    #[Test]
    public function doesNothingOnASiteWithoutTheSetting(): void
    {
        $sections = $this->sections();
        $context  = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')->willReturn($sections);
        $context->method('settings')
            ->willThrowException(UnknownSettingException::section('seo', StrategySection::NAME));

        (new SettingsStrategies($context))->move();

        static::assertSame([], $sections->overrides);
    }

    #[Test]
    public function handsASavedStrategyToTheSiteAndTakesItOutOfTheSettings(): void
    {
        $sections = $this->sections();
        $settings = $this->settings([self::PAGES => self::SAVED]);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame(
            ['images, section', ''],
            [
                $sections->block(self::PAGES)?->element->child('image')?->text,
                $settings->get(StrategySection::SECTIONS),
            ],
        );
    }

    #[Test]
    public function handsEachSectionsStrategyOver(): void
    {
        $sections = $this->sections();

        (new SettingsStrategies($this->context(
            $sections,
            $this->settings([self::PAGES => self::SAVED, self::NEWS => '<seo type="news" schema="Article"/>']),
        )))->move();

        static::assertSame([self::PAGES, self::NEWS], array_keys($sections->overrides));
    }

    #[Test]
    public function handsOverTheStrategiesBesideAnEntryThatIsNotOne(): void
    {
        $sections = $this->sections();
        $settings = new ArraySettings(StrategySection::define(), [
            StrategySection::SECTIONS => '{"site.news.article": {"type": "news"}, "site.page.home": "<seo type=\"page\"/>"}',
        ]);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame([self::PAGES], array_keys($sections->overrides));
    }

    #[Test]
    public function keepsAStrategyTheSiteWouldNotTakeAndSaysSo(): void
    {
        $sections = $this->sections(BlockNotSavedException::refused('The site\'s data folder can\'t be written to'));
        $settings = $this->settings([self::PAGES => self::SAVED, 'site.old.gone' => self::SAVED]);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame(
            [
                json_encode([self::PAGES => self::SAVED], JSON_UNESCAPED_SLASHES),
                1,
                'warning',
                ['section' => self::PAGES, 'reason' => 'The site\'s data folder can\'t be written to'],
            ],
            [
                $settings->get(StrategySection::SECTIONS),
                count($this->logger->entries()),
                $this->logger->entries()[0]['level'] ?? null,
                $this->logger->entries()[0]['context'] ?? null,
            ],
        );
    }

    #[Test]
    public function leavesASectionTheSiteAlreadyOverridesAsItIs(): void
    {
        $sections = $this->sections();
        $sections->override(self::PAGES, Element::parse('<seo type="page" schema="AboutPage"/>'), '');
        $settings = $this->settings([self::PAGES => self::SAVED]);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame(
            ['AboutPage', ''],
            [
                $sections->block(self::PAGES)?->element->attribute('schema'),
                $settings->get(StrategySection::SECTIONS),
            ],
        );
    }

    #[Test]
    public function letsGoOfAStrategyThatNoLongerReadsAsABlock(): void
    {
        $sections = $this->sections();
        $settings = $this->settings([self::PAGES => '<seo>']);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame([[], ''], [$sections->overrides, $settings->get(StrategySection::SECTIONS)]);
    }

    #[Test]
    public function letsGoOfAStrategyWhoseSectionHasNoBlockNow(): void
    {
        $sections = $this->sections();
        $settings = $this->settings(['site.old.gone' => self::SAVED]);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame([[], ''], [$sections->overrides, $settings->get(StrategySection::SECTIONS)]);
    }

    #[Test]
    #[DataProvider('nothingProvider')]
    public function writesNothingWhereTheSettingsHoldNoStrategy(mixed $stored): void
    {
        $sections = $this->sections();
        $settings = new ArraySettings(StrategySection::define(), [StrategySection::SECTIONS => $stored]);

        (new SettingsStrategies($this->context($sections, $settings)))->move();

        static::assertSame([[], []], [$settings->saved, $sections->overrides]);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->logger = new MemoryLogger();
    }

    private function context(OverridableSections $sections, ArraySettings $settings): ExtensionContextInterface
    {
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')->willReturn($sections);
        $context->method('settings')->willReturn($settings);
        $context->method('logger')->willReturn($this->logger);

        return $context;
    }

    private function sections(?BlockNotSavedException $refusal = null): OverridableSections
    {
        return new OverridableSections(
            [
                self::PAGES => new SectionBlock(self::PAGES, Element::parse('<seo type="page"/>'), ['title', 'images']),
                self::NEWS  => new SectionBlock(self::NEWS, Element::parse('<seo type="news"/>'), ['title']),
            ],
            $refusal,
        );
    }

    /**
     * @param array<string, string> $saved Each section's block as XML
     */
    private function settings(array $saved): ArraySettings
    {
        return new ArraySettings(StrategySection::define(), [
            StrategySection::SECTIONS => json_encode($saved, JSON_UNESCAPED_SLASHES),
        ]);
    }
}

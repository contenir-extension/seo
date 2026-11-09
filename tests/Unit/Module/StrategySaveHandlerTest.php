<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Seo\Module\StrategySaveHandler;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Strategy\StrategyNotSaved;
use Contenir\Extension\Seo\Strategy\StrategyOverrides;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[Group('unit')]
final class StrategySaveHandlerTest extends TestCase
{
    private ArraySettings $settings;

    protected function setUp(): void
    {
        $this->settings = $this->settings();
    }

    private const string SECTION = 'library.projects.project';

    private const string BACK = '/admin/extension/seo/strategy?section=library.projects.project';

    #[Test]
    public function returnsASectionToTheSitesDefaultsWhenAsked(): void
    {
        $this->handle(['section' => self::SECTION, 'version' => '', 'schema' => 'Article']);
        $result = $this->handle([
            'section' => self::SECTION,
            'version' => (new StrategyOverrides($this->settings))->version(),
            'reset'   => '1',
        ]);

        static::assertSame(
            [
                '/admin/extension/seo/strategy',
                [['level' => 'success', 'text' => 'Projects are described as the site\'s defaults say again.']],
                false,
            ],
            [$result->url, $result->messages, (new StrategyOverrides($this->settings))->customised(self::SECTION)],
        );
    }

    #[Test]
    public function sendsASaveOfNoSectionToTheList(): void
    {
        static::assertSame(
            ['/admin/extension/seo/strategy', []],
            [
                $this->handle(['section' => 'library.nope.nope'])->url,
                $this->handle(['section' => 'library.nope.nope'])->messages,
            ],
        );
    }

    #[Test]
    public function sendsTheEditorBackToTheFormWhenItWasChangedSinceTheyOpenedIt(): void
    {
        $result = $this->handle(['section' => self::SECTION, 'version' => 'stale', 'schema' => 'Article']);

        static::assertSame(
            [self::BACK, [['level' => 'danger', 'text' => StrategyNotSaved::changed()->getMessage()]]],
            [$result->url, $result->messages],
        );
    }

    #[Test]
    public function sendsTheEditorBackToTheFormWhenTheSettingsCannotBeSaved(): void
    {
        $this->settings = $this->settings(Outcome::Failed);

        $result = $this->handle(['section' => self::SECTION, 'version' => '', 'schema' => 'Article']);

        static::assertSame(
            [self::BACK, [['level' => 'danger', 'text' => StrategyNotSaved::notWritable('Not now.')->getMessage()]]],
            [$result->url, $result->messages],
        );
    }

    #[Test]
    public function sendsTheEditorBackToTheFormWithWhatItAskedThatABlockCantHold(): void
    {
        $result = $this->handle(['section' => self::SECTION, 'version' => '', 'schema' => 'Web Page']);

        static::assertSame(
            [self::BACK, [['level' => 'danger', 'text' => '"Web Page" is not a schema.org type name.']]],
            [$result->url, $result->messages],
        );
    }

    #[Test]
    public function sendsTheEditorToTheListOnceSaved(): void
    {
        $result = $this->handle(['section' => self::SECTION, 'version' => '', 'schema' => 'Article']);

        static::assertSame(
            [
                '/admin/extension/seo/strategy',
                [['level' => 'success', 'text' => 'Saved how Projects are described for search.']],
            ],
            [$result->url, $result->messages],
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function handle(array $body): HandlerResult
    {
        $block    = new SectionBlock(self::SECTION, Element::parse('<seo type="project"/>'), ['title']);
        $sections = $this->createStub(SectionsInterface::class);
        $sections->method('block')->willReturnCallback(static fn(string $section): ?SectionBlock => self::SECTION
            === $section
                ? $block
                : null);
        $records = $this->createStub(RecordsInterface::class);
        $records->method('sections')->willReturn([new LinkableSection(self::SECTION, 'Project', heading: 'Projects')]);
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturn($records);
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')->willReturn($sections);
        $context->method('services')->willReturn($services);
        $context->method('settings')->willReturn($this->settings);

        return (new StrategySaveHandler())->handle(
            (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/strategy-save')
                ->withParsedBody($body),
            $context,
        );
    }

    private function settings(Outcome $outcome = Outcome::Done): ArraySettings
    {
        return new ArraySettings(StrategySection::define(), [], $outcome, 'Not now.');
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Seo\Module\StrategySaveHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[Group('unit')]
final class StrategySaveHandlerTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string BACK = '/admin/extension/seo/strategy?section=library.projects.project';

    /**
     * @return array<string, array{BlockNotSavedException, string}>
     */
    public static function refusalProvider(): array
    {
        return [
            'where it does not save'       => [
                BlockNotSavedException::notAllowed(),
                'This site does not save its configuration here. Change admin.xml where the site is developed.',
            ],
            'a file that has changed'      => [
                BlockNotSavedException::changed(),
                'The site\'s configuration changed since this was opened. Open it again to see what changed.',
            ],
            'a result that would not load' => [
                BlockNotSavedException::refused('It would not load.'),
                'It would not load.',
            ],
        ];
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
    public function sendsTheEditorBackToTheFormWithWhatItAskedThatABlockCantHold(): void
    {
        $result = $this->handle(['section' => self::SECTION, 'version' => 'v1', 'schema' => 'Web Page']);

        static::assertSame(
            [self::BACK, [['level' => 'danger', 'text' => '"Web Page" is not a schema.org type name.']]],
            [$result->url, $result->messages],
        );
    }

    #[Test]
    #[DataProvider('refusalProvider')]
    public function sendsTheEditorBackToTheFormWithWhyTheHostRefused(
        BlockNotSavedException $refusal,
        string $message,
    ): void {
        $result = $this->handle(['section' => self::SECTION, 'version' => 'v1'], $refusal);

        static::assertSame([self::BACK, [['level' => 'danger', 'text' => $message]]], [
            $result->url,
            $result->messages,
        ]);
    }

    #[Test]
    public function sendsTheEditorToTheListOnceSaved(): void
    {
        $result = $this->handle(['section' => self::SECTION, 'version' => 'v1', 'schema' => 'Article']);

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
    private function handle(
        array $body,
        ?BlockNotSavedException $refusal = null,
    ): HandlerResult {
        $block    = new SectionBlock(self::SECTION, Element::parse('<seo type="project"/>'), ['title']);
        $sections = $this->createStub(SectionsInterface::class);
        $sections->method('block')->willReturnCallback(static fn(string $section): ?SectionBlock => self::SECTION
            === $section
                ? $block
                : null);
        if (null !== $refusal) {
            $sections->method('save')->willThrowException($refusal);
        }

        $records = $this->createStub(RecordsInterface::class);
        $records->method('sections')->willReturn([new LinkableSection(self::SECTION, 'Project', heading: 'Projects')]);
        $services = $this->createStub(ContainerInterface::class);
        $services->method('get')->willReturn($records);
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('sections')->willReturn($sections);
        $context->method('services')->willReturn($services);

        return (new StrategySaveHandler())->handle(
            (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/strategy-save')
                ->withParsedBody($body),
            $context,
        );
    }
}

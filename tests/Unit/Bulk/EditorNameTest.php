<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Seo\Bulk\EditorName;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[Group('unit')]
#[Group('bulk')]
final class EditorNameTest extends TestCase
{
    #[Test]
    public function namesTheEditorSignedIn(): void
    {
        $editor = $this->createStub(CurrentEditorInterface::class);
        $editor->method('current')->willReturn(new Editor(7, 'Ann Editor'));

        static::assertSame('Ann Editor', EditorName::of($this->context($editor)));
    }

    #[Test]
    public function saysSomeoneWhenNoEditorIsSignedIn(): void
    {
        $editor = $this->createStub(CurrentEditorInterface::class);
        $editor->method('current')->willReturn(null);

        static::assertSame(EditorName::UNKNOWN, EditorName::of($this->context($editor)));
    }

    #[Test]
    public function saysSomeoneWhenTheHostKnowsNoEditors(): void
    {
        static::assertSame(EditorName::UNKNOWN, EditorName::of($this->context(null)));
    }

    private function context(?CurrentEditorInterface $editor): ExtensionContextInterface
    {
        $services = new class($editor) implements ContainerInterface {
            public function __construct(
                private readonly ?CurrentEditorInterface $editor,
            ) {}

            public function get(string $id): mixed
            {
                return $this->editor ?? throw new LogicException('No editor service');
            }

            public function has(string $id): bool
            {
                return null !== $this->editor;
            }
        };
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('services')->willReturn($services);

        return $context;
    }
}

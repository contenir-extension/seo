<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Seo\Sections\SeoBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SeoDeclarationTest extends TestCase
{
    #[Test]
    public function aBlockIsAutomaticAndTakesContentForBothRolesUnlessItSaysOtherwise(): void
    {
        $block = SeoDeclaration::read(Element::parse('<seo type="page"/>'));

        static::assertSame([true, ['description', 'image']], [$block->automatic, $block->content]);
    }

    #[Test]
    public function aBlockWithoutATypeIsReadWithNone(): void
    {
        static::assertSame(['', null], [
            SeoDeclaration::read(Element::parse('<seo schema=" "/>'))->type,
            SeoDeclaration::read(Element::parse('<seo schema=" "/>'))->schema,
        ]);
    }

    #[Test]
    public function anEmptyBlockTakesTheConventions(): void
    {
        static::assertEquals(new SeoBlock('project'), SeoDeclaration::read(Element::parse('<seo type=" project "/>')));
    }

    #[Test]
    public function anEmptyOverridesColumnIsNone(): void
    {
        static::assertNull(SeoDeclaration::read(Element::parse(
            '<seo type="project"><advanced> </advanced></seo>',
        ))->advanced);
    }

    #[Test]
    public function namesOnlyTheColumnsTheBlockGivesItself(): void
    {
        static::assertSame(
            ['meta_title', 'title', 'image_lg', 'caption', 'seo'],
            SeoDeclaration::named(Element::parse(
                '<seo type="project"><title>meta_title, title</title><image alt="caption, title">image_lg</image>'
                    . '<advanced>seo</advanced></seo>',
            )),
        );
    }

    #[Test]
    public function readsEachRolesColumnsAltTextRequiredRolesAndOverrides(): void
    {
        static::assertEquals(
            new SeoBlock(
                'project',
                'CreativeWork',
                [
                    ...SeoBlock::CONVENTIONS,
                    'description' => ['meta_description', 'abstract'],
                    'image'       => ['image_lg', 'thumbnail'],
                    'image@alt'   => ['caption', 'title'],
                    'published'   => [],
                ],
                ['description'],
                'seo',
            ),
            SeoDeclaration::read(Element::parse(
                '<seo type="project" schema=" CreativeWork ">'
                    . '<description required="true"> meta_description , ,abstract</description>'
                    . '<image alt="caption, title" required="false">image_lg, thumbnail</image>'
                    . '<advanced>seo, other</advanced>'
                    . '<published/>'
                    . '</seo>',
            )),
        );
    }

    #[Test]
    public function readsTheModeAndWhichRolesTakeContent(): void
    {
        $block = SeoDeclaration::read(Element::parse(
            '<seo type="page" mode=" manual "><description content="false">description</description><image content="true"/></seo>',
        ));

        static::assertSame([false, ['image']], [$block->automatic, $block->content]);
    }
}

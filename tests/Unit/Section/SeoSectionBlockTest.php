<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Section\ImpliedListener;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Listener\CheckMeta;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SeoSectionBlockTest extends TestCase
{
    private static function block(string $xml, array $fields): SectionBlock
    {
        return new SectionBlock('library.projects.project', Element::parse($xml), $fields, linkable: true);
    }

    #[Test]
    public function bindsTheSaveCheckToTheTitleDescriptionAndImageColumnsTheSectionHas(): void
    {
        static::assertEquals(
            [new ImpliedListener(CheckMeta::NAME, RecordPreSave::class, [
                'meta_title',
                'title',
                'description',
                'image_lg',
            ])],
            (new SeoSectionBlock())->listeners(self::block(
                '<seo type="project"><image alt="caption">image_lg, thumbnail</image><published>publish</published></seo>',
                ['title', 'meta_title', 'description', 'image_lg', 'caption', 'publish'],
            )),
        );
    }

    #[Test]
    public function readsEveryColumnTheBlockNamesThatTheSectionHasOnce(): void
    {
        static::assertSame(
            ['seo', 'meta_title', 'title', 'description', 'image_lg', 'publish'],
            (new SeoSectionBlock())->columns(self::block(
                '<seo type="project"><image alt="title">image_lg, Bad-Name, image-lg, a_column_name_longer_than_sixty_four_characters_which_the_host_refuses</image><published>publish</published>'
                    . '<advanced>seo</advanced></seo>',
                [
                    'title',
                    'meta_title',
                    'description',
                    'image_lg',
                    'publish',
                    'seo',
                    'Bad-Name',
                    'image-lg',
                    'a_column_name_longer_than_sixty_four_characters_which_the_host_refuses',
                ],
            )),
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Seo\Section\DerivedContent;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('unit')]
final class DerivedContentTest extends TestCase
{
    #[Test]
    public function describesAPageInPlainTextFromTheFirstTextBlock(): void
    {
        $content = new DerivedContent(['section' => (string) json_encode([
            [
                'type'   => 'text',
                'fields' => ['description' => '<p>First <strong>sentence</strong> here. Second sentence.</p>'],
            ],
        ])]);

        static::assertSame('First sentence here. Second sentence.', $content->description());
    }

    #[Test]
    public function hasAnImageWhereAMediaBlockNamesOne(): void
    {
        $content = new DerivedContent(['section' => (string) json_encode([
            ['type' => 'media', 'fields' => ['images' => ['1']]],
        ])]);

        static::assertSame([null, true], [$content->description(), $content->hasImage()]);
    }

    #[Test]
    public function hasNothingWithoutASectionField(): void
    {
        $content = new DerivedContent([]);

        static::assertSame([null, false], [$content->description(), $content->hasImage()]);
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Strategy;

use Contenir\Extension\Seo\Strategy\FieldLabel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Column and schema names as an editor reads them.
 */
#[Group('unit')]
final class FieldLabelTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function names(): array
    {
        return [
            'a column'              => ['meta_title', 'Meta title'],
            'a plain name'          => ['title', 'Title'],
            'a sized image'         => ['image_lg', 'Image lg'],
            'a schema type'         => ['CreativeWork', 'Creative work'],
            'a single word type'    => ['WebPage', 'Web page'],
            'a hyphenated name'     => ['share-image', 'Share image'],
            'extra space'           => ['  meta   title ', 'Meta title'],
            'capitals beyond ASCII' => ['meta_TÍTULO', 'Meta título'],
            'nothing'               => ['', ''],
        ];
    }

    #[Test]
    #[DataProvider('names')]
    public function readsANameAsPlainWords(string $name, string $label): void
    {
        static::assertSame($label, FieldLabel::of($name));
    }
}

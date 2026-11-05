<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Inspect;

use Contenir\Extension\Seo\Inspect\ShareImageFile;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function base64_decode;
use function file_put_contents;
use function mkdir;

#[Group('unit')]
final class ShareImageFileTest extends TestCase
{
    use TempDirectoryTrait;

    private const string PIXEL = 'iVBORw0KGgoAAAANSUhEUgAAAAIAAAABCAAAAADRSSBWAAAAC0lEQVR4nGNgYAAAAAMAAbitOmMAAAAASUVORK5CYII=';

    /**
     * @return array<string, array{string|null, array{int, int}|null}>
     */
    public static function urlProvider(): array
    {
        return [
            'the file itself' => ['https://x.test/asset/a b.png', [2, 1]],
            'an encoded name' => ['https://x.test/asset/a%20b.png', [2, 1]],
            'its variant'     => ['https://x.test/asset/_variant/share/a b.png', [2, 1]],
            'a path'          => ['/asset/a b.png', [2, 1]],
            'a missing file'  => ['https://x.test/asset/gone.png', null],
            'not an image'    => ['https://x.test/asset/note.txt', null],
            'climbing out'    => ['https://x.test/asset/../../secret.png', null],
            'no address'      => [null, null],
        ];
    }

    /**
     * @param array{int, int}|null $size
     */
    #[Test]
    #[DataProvider('urlProvider')]
    public function findsTheFileAnImageWasMadeFrom(?string $url, ?array $size): void
    {
        static::assertSame($size, (new ShareImageFile($this->directory))->size($url));
    }

    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/public/asset", recursive: true);
        file_put_contents("{$this->directory}/public/asset/a b.png", (string) base64_decode(self::PIXEL, strict: true));
        file_put_contents("{$this->directory}/public/asset/note.txt", data: 'not an image');
    }

    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }
}

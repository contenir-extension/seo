<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Seo\Module\SettingsHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The SEO menu's way into the settings screens.
 */
#[Group('unit')]
final class SettingsHandlerTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function screens(): array
    {
        return [
            'appearance'      => ['appearance', '/admin/settings/common/update/category/seo/section/seo'],
            'verification'    => [
                'verification',
                '/admin/settings/common/update/category/seo/section/seo-verification',
            ],
            'sitemap'         => ['sitemap', '/admin/settings/common/update/category/seo/section/seo-sitemap'],
            'one that is not' => ['nope', '/admin/extension/seo/overview'],
            'none'            => ['', '/admin/extension/seo/overview'],
        ];
    }

    #[Test]
    public function ignoresAScreenThatIsNotText(): void
    {
        $result = (new SettingsHandler())->handle(
            (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/settings')
                ->withQueryParams(['screen' => ['appearance']]),
            $this->createStub(ExtensionContextInterface::class),
        );

        static::assertSame('/admin/extension/seo/overview', $result->url);
    }

    #[Test]
    #[DataProvider('screens')]
    public function sendsThePersonToTheScreenAskedForReadyToEdit(string $screen, string $url): void
    {
        $result = (new SettingsHandler())->handle(
            (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/settings')
                ->withQueryParams('' === $screen ? [] : ['screen' => $screen]),
            $this->createStub(ExtensionContextInterface::class),
        );

        static::assertSame($url, $result->url);
    }
}

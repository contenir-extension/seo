<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Seo\Module\SeoMenu;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_map;

/**
 * The SEO area's section menu: the Overview, Records and Strategy, with the one being drawn current.
 */
#[Group('unit')]
final class SeoMenuTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function screens(): array
    {
        return [
            'the Overview' => ['/admin/extension/seo/overview', 'Overview'],
            'the Records'  => ['/admin/extension/seo/records', 'Records'],
            'the Sections' => ['/admin/extension/seo/strategy/', 'Sections'],
        ];
    }

    #[Test]
    public function leadsEachSettingThroughTheSettingsHandler(): void
    {
        $items = $this->menuFor('/admin/extension/seo/overview')[1]->items;

        static::assertSame(
            [
                ['settings', ['screen' => 'appearance']],
                ['strategy', []],
                ['settings', ['screen' => 'verification']],
                ['settings', ['screen' => 'sitemap']],
            ],
            array_map(static fn($item): array => [$item->handler, $item->query], $items),
        );
    }

    #[Test]
    public function listsTheScreensThenTheSettings(): void
    {
        static::assertSame(
            [
                [null, ['Overview', 'Records']],
                ['Settings', ['Search appearance', 'Sections', 'Site verification', 'Sitemap']],
            ],
            array_map(
                static fn($group): array => [
                    $group->title,
                    array_map(static fn($item): string => $item->label, $group->items),
                ],
                $this->menuFor('/admin/extension/seo/overview'),
            ),
        );
    }

    #[Test]
    public function marksNoneCurrentOnAnotherScreen(): void
    {
        static::assertSame(
            [],
            array_filter(
                $this->menuFor('/admin/extension/seo/run')[0]->items,
                static fn($item): bool => $item->current,
            ),
        );
    }

    #[Test]
    #[DataProvider('screens')]
    public function marksTheScreenBeingDrawnCurrent(string $path, string $label): void
    {
        $current = [];
        foreach ($this->menuFor($path) as $group) {
            foreach ($group->items as $item) {
                if (! $item->current) {
                    continue;
                }

                $current[] = $item->label;
            }
        }

        static::assertSame([$label], $current);
    }

    /**
     * @return list<\Contenir\Extension\Api\Module\MenuGroup>
     */
    private function menuFor(string $path): array
    {
        return (new SeoMenu())->groups(
            new ServerRequest('GET', "https://example.test{$path}"),
            $this->createStub(ExtensionContextInterface::class),
        );
    }
}

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
use function array_values;

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
            'the Strategy' => ['/admin/extension/seo/strategy/', 'Strategy'],
        ];
    }

    #[Test]
    public function listsTheThreeScreensInOneUntitledGroup(): void
    {
        $groups = $this->menuFor('/admin/extension/seo/overview');

        static::assertSame(
            [[null, ['Overview', 'Records', 'Strategy']]],
            array_map(
                static fn($group): array => [
                    $group->title,
                    array_map(static fn($item): string => $item->label, $group->items),
                ],
                $groups,
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
        $current = array_values(array_map(
            static fn($item): string => $item->label,
            array_filter($this->menuFor($path)[0]->items, static fn($item): bool => $item->current),
        ));

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

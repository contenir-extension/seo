<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Module\MenuGroup;
use Contenir\Extension\Api\Module\MenuInterface;
use Contenir\Extension\Api\Module\MenuItem;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function basename;

/**
 * The SEO area's section menu: its screens, the one being drawn marked current, and under Settings the four
 * screens that shape how the site reads in search, wherever each lives.
 */
final class SeoMenu implements MenuInterface
{
    /**
     * @var array<string, string> Each screen's label, by its handler
     */
    private const array SCREENS = [
        OverviewHandler::NAME => 'Overview',
        RecordsHandler::NAME  => 'Records',
    ];

    /** The settings that shape how the site reads in search, each as a link through SettingsHandler. */
    private const array SETTINGS = [
        'Search appearance' => ['screen' => 'appearance'],
        'Sections'          => null,
        'Site verification' => ['screen' => 'verification'],
        'Sitemap'           => ['screen' => 'sitemap'],
    ];

    /**
     * @return list<MenuGroup>
     */
    #[Override]
    public function groups(ServerRequestInterface $request, ExtensionContextInterface $context): array
    {
        $here  = basename($request->getUri()->getPath());
        $items = [];
        foreach (self::SCREENS as $handler => $label) {
            $items[] = new MenuItem($label, $handler, current: $handler === $here);
        }

        $settings = [];
        foreach (self::SETTINGS as $label => $query) {
            $settings[] = null === $query
                ? new MenuItem($label, StrategyHandler::NAME, current: StrategyHandler::NAME === $here)
                : new MenuItem($label, SettingsHandler::NAME, $query);
        }

        return [new MenuGroup(null, $items), new MenuGroup('Settings', $settings)];
    }
}

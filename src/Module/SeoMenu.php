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
use function rtrim;

/**
 * The SEO area's section menu: its three screens, the one being drawn marked current.
 */
final class SeoMenu implements MenuInterface
{
    private const array SCREENS = [
        OverviewHandler::NAME => 'Overview',
        RecordsHandler::NAME  => 'Records',
        StrategyHandler::NAME => 'Strategy',
    ];

    /**
     * @return list<MenuGroup>
     */
    #[Override]
    public function groups(ServerRequestInterface $request, ExtensionContextInterface $context): array
    {
        $here  = basename(rtrim($request->getUri()->getPath(), characters: '/'));
        $items = [];
        foreach (self::SCREENS as $handler => $label) {
            $items[] = new MenuItem($label, (string) $handler, current: (string) $handler === $here);
        }

        return [new MenuGroup(null, $items)];
    }
}

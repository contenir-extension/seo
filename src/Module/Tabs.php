<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Handler\Tab;

/**
 * The tabs across the top of the SEO module's screens.
 */
final class Tabs
{
    /**
     * @return list<Tab>
     */
    public static function of(string $current): array
    {
        return [
            new Tab('Overview', OverviewHandler::NAME, current: OverviewHandler::NAME === $current),
            new Tab('Records', RecordsHandler::NAME, current: RecordsHandler::NAME === $current),
            new Tab('Strategy', StrategyHandler::NAME, current: StrategyHandler::NAME === $current),
        ];
    }
}

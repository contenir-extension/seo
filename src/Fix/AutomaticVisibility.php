<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Fix;

use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Seo\Visibility\Visibility;
use Override;

/**
 * Sets "Search engines" back to Automatic on this environment: production
 * is shown and any copy hidden.
 *
 * @api
 */
final class AutomaticVisibility extends AbstractSettingFix
{
    #[Override]
    protected function done(): string
    {
        return 'Search engines are set to Automatic on this environment.';
    }

    #[Override]
    protected function values(): array
    {
        return [AppearanceSection::VISIBILITY => Visibility::Automatic->value];
    }
}

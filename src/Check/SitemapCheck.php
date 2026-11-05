<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\SeoSettings;
use Override;

/**
 * Whether the site's sitemap is on.
 */
final readonly class SitemapCheck implements CheckInterface
{
    /**
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        return (new SeoSettings())->config($context)->sitemap->enabled
            ? CheckResult::success('The sitemap lists the site\'s pages for search engines.')
            : CheckResult::warning(
                'The sitemap is off, so search engines only find pages by following links.'
                    . ' Turn it on in Site Settings, under Sitemap.',
            );
    }

    #[Override]
    public function label(): string
    {
        return 'Sitemap';
    }
}

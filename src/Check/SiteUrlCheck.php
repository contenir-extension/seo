<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Seo\SiteSettingsFiles;
use Contenir\Seo\SeoConfig;
use Contenir\Seo\SettingKeys;
use Override;

use function sprintf;

/**
 * Warns when the site has no site URL. Without one, Automatic can't tell
 * production from a copy, robots.txt and the sitemap link from whatever
 * host a request names, and the home page has no structured data.
 *
 * @api
 */
final readonly class SiteUrlCheck implements CheckInterface
{
    public function __construct(
        private SiteSettingsFiles $files = new SiteSettingsFiles(),
    ) {}

    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $site = SeoConfig::fromCoreSettings($this->files->read($context->sitePath()))->site;
        if ($site->isSet()) {
            return CheckResult::success(sprintf('Links for search engines are built on %s.', $site->origin));
        }

        return CheckResult::warning(
            'The site has no site URL, so search engines can\'t be kept away from copies of the site.',
            new GuidedFix(
                label: 'Set the site URL',
                explanation: sprintf(
                    'Enter the live site\'s address, such as https://www.example.com, in Site Settings > Site >'
                        . ' Site URL (%s). Until then, every copy of the site is shown to search engines,'
                        . ' robots.txt and the sitemap link from whatever address a visitor used, and the home'
                        . ' page has no structured data.',
                    SettingKeys::SITE_URL,
                ),
            ),
        );
    }

    #[Override]
    public function label(): string
    {
        return 'Site URL for search engines';
    }
}

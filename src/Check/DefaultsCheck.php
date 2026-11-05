<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\SeoSettings;
use Override;

use function array_filter;
use function array_keys;
use function array_pop;
use function implode;
use function sprintf;

/**
 * Whether the site has what its pages fall back on when they have none of
 * their own: a description, a share image and a logo for its organisation.
 * Images need the site's address, so without one this waits on the site URL
 * check. A site whose sections declare no `<seo>` block has no pages that fall
 * back on them, so the check is skipped.
 */
final readonly class DefaultsCheck implements CheckInterface
{
    /**
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        if ([] === $context->sections()->blocks()) {
            return CheckResult::skipped(
                'No section declares an <seo> block in admin.xml, so no page falls back on these.',
            );
        }

        $config = (new SeoSettings())->config($context);
        if (! $config->site->isSet()) {
            return CheckResult::skipped('Set the site URL first: the share image and logo are addressed from it.');
        }

        $missing = array_keys(array_filter([
            'description'       => '' === $config->description,
            'share image'       => null === $config->shareImage,
            'organisation logo' => null === $config->organisation->logo,
        ]));
        if ([] === $missing) {
            return CheckResult::success('The site has a description, a share image and an organisation logo.');
        }

        $last = array_pop($missing);

        return CheckResult::warning(sprintf(
            'The site has no %s, so pages without their own have nothing to fall back on.'
                . ' Add %s in Site Settings, under Search Appearance.',
            [] === $missing ? $last : implode(', ', $missing) . " or {$last}",
            [] === $missing ? 'it' : 'them',
        ));
    }

    #[Override]
    public function label(): string
    {
        return 'Site-wide search defaults';
    }
}

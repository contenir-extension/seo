<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\OneClickFix;
use Contenir\Extension\Seo\Environment;
use Contenir\Extension\Seo\Fix\AutomaticVisibility;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Seo\Visibility\Visibility;
use Override;

use function is_string;
use function sprintf;

/**
 * Warns when production is hidden from search engines (SEO-3), so the
 * live site would drop out of search results, and when a copy of the site
 * is shown to them, so it could compete with the live site.
 *
 * @api
 */
final readonly class VisibilityCheck implements CheckInterface
{
    private Environment $environment;

    public function __construct(?Environment $environment = null)
    {
        $this->environment = $environment ?? new Environment();
    }

    /**
     * @mago-expect analysis:mixed-assignment Settings are untyped; the value is checked here.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $value      = $context->settings(AppearanceSection::NAME)->get(AppearanceSection::VISIBILITY);
        $visibility = Visibility::parse(is_string($value) ? $value : null);
        $production = $this->environment->isProduction();

        if ($production && Visibility::Hide === $visibility) {
            return CheckResult::warning(
                'Production is hidden from search engines, so the live site will drop out of search results.',
                new OneClickFix('Set to Automatic', AutomaticVisibility::class),
            );
        }

        if (! $production && Visibility::Show === $visibility) {
            return CheckResult::warning(
                sprintf(
                    'This %s copy is shown to search engines, so it could appear in search results beside the'
                        . ' live site.',
                    $this->environment->name,
                ),
                new GuidedFix(
                    label: 'Hide this copy',
                    explanation: sprintf(
                        'Setting search engines to Automatic hides this %s copy, as long as its site URL is the'
                            . ' live site\'s. It changes this environment only: production stays in search results.',
                        $this->environment->name,
                    ),
                    handler: AutomaticVisibility::class,
                ),
            );
        }

        return CheckResult::success(
            $production
                ? 'Production is shown to search engines.'
                : sprintf('This %s copy is hidden from search engines.', $this->environment->name),
        );
    }

    #[Override]
    public function label(): string
    {
        return 'Search engines and this environment';
    }
}

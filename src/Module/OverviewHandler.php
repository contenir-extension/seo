<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Check\DefaultsCheck;
use Contenir\Extension\Seo\Check\SitemapCheck;
use Contenir\Extension\Seo\Health\Age;
use Contenir\Extension\Seo\Health\SiteChecks;
use Contenir\Extension\Seo\Health\SiteHealth;
use Contenir\Extension\Seo\Inspect\InspectionStore;
use Contenir\Extension\Seo\Report\SearchReport;
use Contenir\Extension\Seo\SeoSettings;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function max;

/**
 * The SEO area's home: each section described for search, with how many of its
 * records need something under each role, and how the site's own defaults stand.
 */
final readonly class OverviewHandler implements HandlerInterface
{
    public const string NAME = 'overview';

    /**
     * How long ago the site was last asked what it renders for a record, as people read it ("4 minutes"), or
     * null when it never has been.
     */
    public static function checkedAgo(ExtensionContextInterface $context): ?string
    {
        $latest = null;
        foreach (InspectionStore::at($context->sitePath())->load() as $inspection) {
            $latest = max($latest ?? 0, $inspection->at);
        }

        return null === $latest ? null : Age::of($context->clock()->now()->getTimestamp() - $latest);
    }

    /**
     * The site-wide checks that apply to the site, each as the template shows it.
     *
     * @return list<array{label: string, status: string, message: string}>
     *
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    private static function checks(ExtensionContextInterface $context): array
    {
        $checks = [];
        foreach ([new DefaultsCheck(), new SitemapCheck()] as $check) {
            $result = $check->check($context);
            if (Status::Skipped === $result->status) {
                continue;
            }

            $checks[] = ['label' => $check->label(), 'status' => $result->status->value, 'message' => $result->message];
        }

        return $checks;
    }

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When a tab or the template is malformed.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $sections = (new SearchReport($context))->sections();

        return HandlerResult::template(
            'overview',
            'SEO',
            [
                'sections' => $sections,
                'health'   => SiteHealth::of($sections, SiteChecks::of($context)),
                'ago'      => self::checkedAgo($context),
                'roles'    => SearchReport::ROLES,
                'settings' => SettingsLinks::all(),
                'records'  => (new SeoSettings())->config($context)->records,
                'checks'   => self::checks($context),
            ],
            Tabs::of(self::NAME),
        );
    }
}

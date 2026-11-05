<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Check\DefaultsCheck;
use Contenir\Extension\Seo\Check\SitemapCheck;
use Contenir\Extension\Seo\Report\SearchReport;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;

/**
 * The SEO area's home: each section described for search, with how many of its
 * records need something under each role, and how the site's own defaults stand.
 */
final readonly class OverviewHandler implements HandlerInterface
{
    public const string NAME = 'overview';

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When a tab or the template is malformed.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        return HandlerResult::template(
            'overview',
            'SEO',
            [
                'sections' => (new SearchReport($context))->sections(),
                'roles'    => SearchReport::ROLES,
                'settings' => SettingsLinks::all(),
                'checks'   => array_map(
                    static function (CheckInterface $check) use ($context): array {
                        $result = $check->check($context);

                        return [
                            'label'   => $check->label(),
                            'status'  => $result->status->value,
                            'message' => $result->message,
                        ];
                    },
                    [new DefaultsCheck(), new SitemapCheck()],
                ),
            ],
            Tabs::of(self::NAME),
        );
    }
}

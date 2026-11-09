<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * Takes the person to one of the SEO settings screens, ready to edit, so the SEO menu can lead to them from
 * its own area. The screen itself checks who may change it.
 */
final readonly class SettingsHandler implements HandlerInterface
{
    public const string NAME = 'settings';

    /**
     * @mago-expect analysis:mixed-assignment A query value is untyped; only text names a screen.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $asked = $request->getQueryParams()['screen'] ?? '';

        return HandlerResult::redirect(
            (is_string($asked) ? SettingsLinks::edit($asked) : null) ?? Urls::to(OverviewHandler::NAME),
        );
    }
}

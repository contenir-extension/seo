<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Report\SectionHeadings;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Strategy\InvalidStrategy;
use Contenir\Extension\Seo\Strategy\SectionStrategy;
use Contenir\Extension\Seo\Strategy\StrategyEdit;
use Contenir\Extension\Seo\Strategy\StrategyNotSaved;
use Contenir\Extension\Seo\Strategy\StrategyOverrides;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * Saves one section's strategy for this environment, or returns it to what admin.xml says, and says why where it
 * can't: the strategy changed since the form was opened, or the form asks for something a `<seo>` block can't hold.
 */
final readonly class StrategySaveHandler implements HandlerInterface
{
    public const string NAME = 'strategy-save';

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws NotFoundExceptionInterface When the host lends no records.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $body    = $request->getParsedBody();
        $section = is_array($body) && is_string($body['section'] ?? null) ? $body['section'] : '';
        $version = is_array($body) && is_string($body['version'] ?? null) ? $body['version'] : '';
        $block   = SeoBlocks::of($context)->one($section);
        if ('POST' !== $request->getMethod() || ! is_array($body) || null === $block) {
            return HandlerResult::redirect(Urls::to(StrategyHandler::NAME));
        }

        $back      = Urls::to(StrategyHandler::NAME, ['section' => $section]);
        $current   = SectionStrategy::of($block, SectionHeadings::of($context)[$section] ?? $section);
        $overrides = StrategyOverrides::at($context->sitePath());

        try {
            if ('1' === ($body['reset'] ?? null)) {
                $overrides->reset($section, $version);

                return HandlerResult::redirect(Urls::to(StrategyHandler::NAME))->withMessage(
                    'success',
                    "{$current->title} are described as the site's defaults say again.",
                );
            }

            $overrides->save($section, (new StrategyEdit())->apply($block->element, $current, $body), $version);
        } catch (InvalidStrategy $invalid) {
            return HandlerResult::redirect($back)->withMessage('danger', $invalid->getMessage());
        } catch (StrategyNotSaved $refused) {
            return HandlerResult::redirect($back)->withMessage('danger', $refused->getMessage());
        }

        return HandlerResult::redirect(Urls::to(StrategyHandler::NAME))->withMessage(
            'success',
            "Saved how {$current->title} are described for search.",
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Report\SectionHeadings;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Strategy\InvalidStrategy;
use Contenir\Extension\Seo\Strategy\SectionStrategy;
use Contenir\Extension\Seo\Strategy\StrategyEdit;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * Saves one section's strategy for this environment, or returns it to what admin.xml says, and says why where it
 * can't: the strategy changed since the form was opened, the site would not keep it, or the form asks for
 * something a `<seo>` block can't hold.
 */
final readonly class StrategySaveHandler implements HandlerInterface
{
    public const string NAME = 'strategy-save';

    /**
     * Why the site would not keep the change, as the editor is told.
     */
    private static function why(BlockNotSavedException $refused): string
    {
        return BlockNotSavedException::CHANGED === $refused->reason
            ? 'This was changed by someone else since you opened it. Open it again to see what changed.'
            : "The change was not saved. {$refused->getMessage()}.";
    }

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

        $back     = Urls::to(StrategyHandler::NAME, ['section' => $section]);
        $current  = SectionStrategy::of($block, SectionHeadings::of($context)[$section] ?? $section);
        $sections = $context->sections();

        try {
            if ('1' === ($body['reset'] ?? null)) {
                $sections->restore($section, $version);

                return HandlerResult::redirect(Urls::to(StrategyHandler::NAME))->withMessage(
                    'success',
                    "{$current->title} are described as the site's defaults say again.",
                );
            }

            $sections->override($section, (new StrategyEdit())->apply($block->element, $current, $body), $version);
        } catch (InvalidStrategy $invalid) {
            return HandlerResult::redirect($back)->withMessage('danger', $invalid->getMessage());
        } catch (BlockNotSavedException $refused) {
            return HandlerResult::redirect($back)->withMessage('danger', self::why($refused));
        }

        return HandlerResult::redirect(Urls::to(StrategyHandler::NAME))->withMessage(
            'success',
            "Saved how {$current->title} are described for search.",
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Report\SectionHeadings;
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
 * Saves one section's strategy into the site's admin.xml through the host, and says why where the host would
 * not: it does not save here, the file changed since the form was opened, or the form asks for something a
 * `<seo>` block can't hold.
 */
final readonly class StrategySaveHandler implements HandlerInterface
{
    public const string NAME = 'strategy-save';

    private static function because(BlockNotSavedException $refused): string
    {
        return match ($refused->reason) {
            BlockNotSavedException::NOT_ALLOWED
                => 'This site does not save its configuration here. Change admin.xml where the site is developed.',
            BlockNotSavedException::CHANGED
                => 'The site\'s configuration changed since this was opened. Open it again to see what changed.',
            default                             => $refused->getMessage(),
        };
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
        $block   = $context->sections()->block($section);
        if ('POST' !== $request->getMethod() || ! is_array($body) || null === $block) {
            return HandlerResult::redirect(Urls::to(StrategyHandler::NAME));
        }

        $back    = Urls::to(StrategyHandler::NAME, ['section' => $section]);
        $current = SectionStrategy::of($block, SectionHeadings::of($context)[$section] ?? $section);

        try {
            $context->sections()->save(
                $section,
                (new StrategyEdit())->apply($block->element, $current, $body),
                $version,
            );
        } catch (InvalidStrategy $invalid) {
            return HandlerResult::redirect($back)->withMessage('danger', $invalid->getMessage());
        } catch (BlockNotSavedException $refused) {
            return HandlerResult::redirect($back)->withMessage('danger', self::because($refused));
        }

        return HandlerResult::redirect(Urls::to(StrategyHandler::NAME))->withMessage(
            'success',
            "Saved how {$current->title} are described for search.",
        );
    }
}

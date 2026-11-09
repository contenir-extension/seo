<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Report\SectionHeadings;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Strategy\SectionStrategy;
use Contenir\Extension\Seo\Strategy\StrategyOverrides;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_filter;
use function array_map;
use function array_values;

/**
 * How each section's pages are described for search: its schema type and where its title, description and
 * image are read from, listed, and one section's opened to change where the site saves its configuration.
 */
final readonly class StrategyHandler implements HandlerInterface
{
    public const string NAME = 'strategy';

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When a tab or the template is malformed.
     * @throws NotFoundExceptionInterface When the host lends no records.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $titles     = SectionHeadings::of($context);
        $overrides  = StrategyOverrides::at($context->sitePath());
        $strategies = [];
        foreach (SeoBlocks::of($context)->all() as $block) {
            $strategies[] = SectionStrategy::of($block, $titles[$block->section] ?? $block->section);
        }

        $asked   = $request->getQueryParams()['section'] ?? '';
        $editing = null;
        foreach ($strategies as $strategy) {
            if ($strategy->section !== $asked) {
                continue;
            }

            $editing = $strategy;
        }

        return HandlerResult::template(
            'strategy',
            'SEO',
            [
                'strategies' => $strategies,
                'editing'    => $editing,
                'saves'      => true,
                'version'    => $overrides->version(),
                'customised' => array_values(array_filter(
                    array_map(static fn(SectionStrategy $one): string => $one->section, $strategies),
                    $overrides->customised(...),
                )),
                'roles'      => SectionStrategy::ROLES,
            ],
        );
    }
}

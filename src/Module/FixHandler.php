<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Bulk\ChangePlan;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Bulk\SectionTitles;
use Contenir\Extension\Seo\Bulk\Selection;
use Contenir\Extension\Seo\Report\SearchReport;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function count;
use function is_array;
use function is_string;

/**
 * Works out what a fix would change on the records picked on the Records screen, changing nothing, and shows it
 * as a table of record, property, before and after for the person to look over and, for descriptions, edit.
 */
final readonly class FixHandler implements HandlerInterface
{
    public const string NAME = 'records-fix';

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When a tab or the template is malformed.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $body = $request->getParsedBody();
        $kind = FixKind::tryFrom(is_array($body) && is_string($body['fix'] ?? null) ? $body['fix'] : '');
        if ('POST' !== $request->getMethod() || null === $kind || ! is_array($body)) {
            return HandlerResult::redirect(Urls::to(RecordsHandler::NAME));
        }

        $sections = (new SearchReport($context))->sections();
        $changes  = (new ChangePlan($context->sections(), SectionTitles::of($sections)))->build(
            $kind,
            Selection::of($body, $sections),
        );
        if ([] === $changes) {
            return HandlerResult::back()->withMessage(
                'warning',
                "Nothing to change: no record picked needs \"{$kind->label()}\".",
            );
        }

        return HandlerResult::template(
            'review',
            $kind->label(),
            ['kind' => $kind, 'changes' => $changes, 'count' => count($changes)],
            Tabs::of(RecordsHandler::NAME),
        );
    }
}

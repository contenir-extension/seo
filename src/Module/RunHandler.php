<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Bulk\RunStore;
use Override;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * How a bulk fix went: each change, what became of it, and a button to undo it while its values are fresh.
 */
final readonly class RunHandler implements HandlerInterface
{
    public const string NAME = 'records-run';

    /**
     * @throws InvalidDeclarationException When a tab or the template is malformed.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        /** @var mixed $id */
        $id  = $request->getQueryParams()['run'] ?? null;
        $run = is_string($id) ? RunStore::at($context->sitePath())->find($id) : null;
        if (null === $run) {
            return HandlerResult::redirect(Urls::to(RecordsHandler::NAME))->withMessage(
                'warning',
                'That fix is no longer kept.',
            );
        }

        $now = $context->clock()->now()->getTimestamp();

        return HandlerResult::template(
            'run',
            $run->kind->label(),
            ['run' => $run, 'undoable' => $run->undoable($now), 'now' => $now],
        );
    }
}

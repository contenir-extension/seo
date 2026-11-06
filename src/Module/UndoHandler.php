<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Queue\TaskQueueInterface;
use Contenir\Extension\Seo\Bulk\RunStore;
use Contenir\Extension\Seo\Bulk\UndoChanges;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * Queues the undo of a bulk fix, while its values are fresh; the screen of the fix says how it went.
 */
final readonly class UndoHandler implements HandlerInterface
{
    public const string NAME = 'records-undo';

    /**
     * @throws InvalidDeclarationException When the task can't be queued.
     * @throws ContainerExceptionInterface When the host's services can't be reached.
     * @throws NotFoundExceptionInterface When the host lends no task queue.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $body = $request->getParsedBody();
        /** @var mixed $id */
        $id  = is_array($body) ? $body['run'] ?? null : null;
        $run = is_string($id) ? RunStore::at($context->sitePath())->find($id) : null;
        if ('POST' !== $request->getMethod() || null === $run) {
            return HandlerResult::redirect(Urls::to(RecordsHandler::NAME));
        }

        if (! $run->undoable($context->clock()->now()->getTimestamp())) {
            return HandlerResult::redirect(Urls::to(RunHandler::NAME, ['run' => $run->id]))
                ->withMessage('warning', 'That fix can no longer be undone.');
        }

        $queue = $context->services()->get(TaskQueueInterface::class);
        $queue->enqueue($context->handle()->value, UndoChanges::NAME, ['run' => $run->id]);

        return HandlerResult::redirect(Urls::to(RunHandler::NAME, ['run' => $run->id]))
            ->withMessage('success', 'Undoing it now.');
    }
}

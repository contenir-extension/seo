<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Queue\TaskQueueInterface;
use Contenir\Extension\Seo\Bulk\ApplyChanges;
use Contenir\Extension\Seo\Bulk\ChangePlan;
use Contenir\Extension\Seo\Bulk\EditedChanges;
use Contenir\Extension\Seo\Bulk\EditorName;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Bulk\Run;
use Contenir\Extension\Seo\Bulk\RunChange;
use Contenir\Extension\Seo\Bulk\RunStore;
use Contenir\Extension\Seo\Bulk\SectionTitles;
use Contenir\Extension\Seo\Bulk\Selection;
use Contenir\Extension\Seo\Report\SearchReport;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;

use function array_keys;
use function array_map;
use function bin2hex;
use function is_array;
use function is_string;
use function random_bytes;

/**
 * Starts a bulk fix: it works out the changes again from the records the review table was for, so nothing the
 * form says but the records, the kind of fix and the edited text is trusted, keeps them as a run, and queues
 * the run to be applied as the signed-in editor.
 */
final readonly class ApplyHandler implements HandlerInterface
{
    public const string NAME = 'records-apply';

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When the task can't be queued.
     * @throws NotFoundExceptionInterface When the host lends no task queue.
     * @throws RandomException When no id can be made.
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

        $after    = is_array($body['after'] ?? null) ? $body['after'] : [];
        $sections = (new SearchReport($context))->sections();
        $changes  = EditedChanges::of(
            (new ChangePlan($context->sections(), SectionTitles::of($sections)))->build(
                $kind,
                Selection::parse(array_keys($after)),
            ),
            $after,
        );
        if ([] === $changes) {
            return HandlerResult::redirect(Urls::to(RecordsHandler::NAME))->withMessage(
                'warning',
                'There was nothing left to change.',
            );
        }

        $run = new Run(
            bin2hex(random_bytes(6)),
            $context->clock()->now()->getTimestamp(),
            EditorName::of($context),
            $kind,
            array_map(RunChange::of(...), $changes),
        );
        RunStore::at($context->sitePath())->put($run);
        $queue = $context->services()->get(TaskQueueInterface::class);
        $queue->enqueue($context->handle()->value, ApplyChanges::NAME, ['run' => $run->id]);

        return HandlerResult::redirect(Urls::to(RunHandler::NAME, ['run' => $run->id]));
    }
}

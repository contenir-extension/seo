<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Contenir\Extension\Seo\Bulk\Change;
use Contenir\Extension\Seo\Bulk\ChangePlan;
use Contenir\Extension\Seo\Bulk\DescriptionWrite;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Bulk\RunChange;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function is_string;

/**
 * Makes the description a record's Section field gives the record's own, as the Records screen's "Write
 * descriptions" does for one record: a page that shows the text, and, when it is saved, the same write through
 * the host's record updates. The text saved is the text shown; if the record's content has changed since the
 * page was opened, nothing is written and the new text is shown instead.
 */
final readonly class UseHandler implements HandlerInterface
{
    public const string NAME = 'use';

    /**
     * @param array<array-key, mixed> $source
     */
    private static function text(array $source, string $key): string
    {
        return is_string($source[$key] ?? null) ? $source[$key] : '';
    }

    /**
     * @throws ContainerExceptionInterface When the host's services can't be reached.
     * @throws InvalidDeclarationException When the template is malformed or a change names no field.
     * @throws NotFoundExceptionInterface When the host has no record updates after saying it has.
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $posted = 'POST' === $request->getMethod();
        $body   = $request->getParsedBody();
        $source = $posted && is_array($body) ? $body : $request->getQueryParams();
        $record = self::text($source, 'section');
        $id     = self::text($source, 'id');
        if ('' === $record || '' === $id || ! $context->services()->has(RecordUpdatesInterface::class)) {
            return HandlerResult::redirect(Urls::to(RecordsHandler::NAME))->withMessage(
                'warning',
                'That record\'s description can\'t be written here.',
            );
        }

        $reference = new RecordReference($record, $id);
        $back      = $this->back($context, $reference);
        $change    = (new ChangePlan($context->sections()))->build(FixKind::WriteDescriptions, [$reference])[0] ?? null;

        return match (true) {
            null === $change => HandlerResult::redirect($back)->withMessage(
                'warning',
                'Nothing to write: the record has a description already, or its content gives none.',
            ),
            ! $posted => HandlerResult::template('use', 'Use as its own description', [
                'change' => $change,
                'back'   => $back,
            ]),
            self::text($source, 'after') !== $change->after => HandlerResult::redirect(
                Urls::to(self::NAME, ['section' => $record, 'id' => $id]),
            )->withMessage('warning', 'The record\'s content changed since this was opened. Check the text again.'),
            default => $this->write($context, $change, $back),
        };
    }

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws NotFoundExceptionInterface When the host has no records after saying it has.
     */
    private function back(ExtensionContextInterface $context, RecordReference $record): string
    {
        $services = $context->services();
        $url      = $services->has(RecordsInterface::class)
            ? $services->get(RecordsInterface::class)->describe($record)->editUrl ?? null
            : null;

        return $url ?? Urls::to(RecordsHandler::NAME);
    }

    /**
     * @throws ContainerExceptionInterface When the host's record updates can't be reached.
     * @throws InvalidDeclarationException When the change names no field.
     * @throws NotFoundExceptionInterface When the host has no record updates after saying it has.
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    private function write(ExtensionContextInterface $context, Change $change, string $back): HandlerResult
    {
        $settled = (new DescriptionWrite(
            $context->services()->get(RecordUpdatesInterface::class),
            $context->sections(),
        ))
            ->run($change);

        return RunChange::DONE === $settled->status
            ? HandlerResult::redirect($back)->withMessage('success', 'Saved as the record\'s own description.')
            : HandlerResult::redirect($back)->withMessage(
                'danger',
                '' === $settled->message ? 'The description could not be saved.' : $settled->message,
            );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Panel;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\RecordView;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Contenir\Extension\Seo\Bulk\ChangePlan;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Module\Urls;
use Contenir\Extension\Seo\Module\UseHandler;
use Psr\Container\ContainerExceptionInterface;

use function in_array;

/**
 * The panel's offer to save the description a record's content gives as the record's own, made only where the
 * host can save the record and the bulk "Write descriptions" has a description to write for it.
 *
 * @internal
 */
final class OwnDescription
{
    /**
     * @param list<string> $derived The roles the record's content gives
     *
     * @throws ContainerExceptionInterface When the host's services can't be reached.
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    public static function link(ExtensionContextInterface $context, RecordView $record, array $derived): ?Link
    {
        if (
            ! in_array('description', $derived, strict: true)
            || ! $context->services()->has(RecordUpdatesInterface::class)
            || [] === (new ChangePlan($context->sections()))->build(FixKind::WriteDescriptions, [$record->reference()])
        ) {
            return null;
        }

        return new Link('Use description as its own', Urls::to(UseHandler::NAME, [
            'section' => $record->section,
            'id'      => $record->reference()->id,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Editor\CurrentEditorInterface;
use Psr\Container\ContainerExceptionInterface;

/**
 * Who a run is put down to: the signed-in editor, or "Someone" where the host doesn't say.
 */
final class EditorName
{
    public const string UNKNOWN = 'Someone';

    /**
     * @throws ContainerExceptionInterface When the host's services can't be reached.
     */
    public static function of(ExtensionContextInterface $context): string
    {
        $editor = $context->services()->has(CurrentEditorInterface::class)
            ? $context->services()->get(CurrentEditorInterface::class)
            : null;

        return $editor instanceof CurrentEditorInterface ? $editor->current()->name ?? self::UNKNOWN : self::UNKNOWN;
    }
}

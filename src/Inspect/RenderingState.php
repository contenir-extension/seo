<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\SeoSettings;

/**
 * What the add-on knows about how the site renders its pages, besides each record's own columns: whether the
 * records switch is on. An answer the site gave under another state is out of date, however old it is.
 */
final class RenderingState
{
    /**
     * @throws UnknownSettingException Never: every setting read is declared.
     */
    public static function of(ExtensionContextInterface $context): string
    {
        return (new SeoSettings())->config($context)->records ? 'records:on' : 'records:off';
    }
}

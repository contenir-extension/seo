<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Seo\Title\TitlePosition;
use Contenir\Seo\Title\TitleTemplate;

use function is_string;

/**
 * The site's title template, as Search Appearance sets it: a page's title
 * with the site name and separator, where the site puts them.
 */
final class SiteTitle
{
    /**
     * @throws UnknownSettingException When the host has no Search Appearance settings.
     */
    public static function template(ExtensionContextInterface $context): TitleTemplate
    {
        $settings = $context->settings(AppearanceSection::NAME);
        /** @var mixed $siteName */
        $siteName = $settings->get(AppearanceSection::SITE_NAME);
        /** @var mixed $separator */
        $separator = $settings->get(AppearanceSection::TITLE_SEPARATOR);
        /** @var mixed $position */
        $position = $settings->get(AppearanceSection::TITLE_POSITION);

        return new TitleTemplate(
            is_string($siteName) ? $siteName : '',
            is_string($separator) ? $separator : TitleTemplate::SEPARATOR,
            TitlePosition::tryFrom(is_string($position) ? $position : '') ?? TitlePosition::After,
        );
    }
}

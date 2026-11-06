<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\Check\DefaultsCheck;
use Contenir\Extension\Seo\Check\SitemapCheck;

/**
 * The site-wide checks that count toward the site's health, as SiteHealth takes them: each by the kind of
 * issue it stands for, true while it fails. A check that is skipped does not apply to this site, so it is
 * left out and costs nothing.
 */
final class SiteChecks
{
    /**
     * @return array<string, bool>
     *
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    public static function of(ExtensionContextInterface $context): array
    {
        $checks = [
            IssueKind::DefaultsNotSet->value => new DefaultsCheck(),
            IssueKind::SitemapOff->value     => new SitemapCheck(),
        ];

        $found = [];
        foreach ($checks as $kind => $check) {
            $status = self::statusOf($check, $context);
            if (Status::Skipped === $status) {
                continue;
            }

            $found[$kind] = Status::Success !== $status;
        }

        return $found;
    }

    /**
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    private static function statusOf(CheckInterface $check, ExtensionContextInterface $context): Status
    {
        return $check->check($context)->status;
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit;

use Contenir\Extension\Api\Diagnostic\DiagnosticDeclaration;
use Contenir\Extension\Api\Field\FieldTypeDeclaration;
use Contenir\Extension\Api\Registration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Seo\Check\SiteUrlCheck;
use Contenir\Extension\Seo\Check\VisibilityCheck;
use Contenir\Extension\Seo\Field\BingTokenField;
use Contenir\Extension\Seo\Field\GoogleTokenField;
use Contenir\Extension\Seo\Field\MetaTokenField;
use Contenir\Extension\Seo\Field\PinterestTokenField;
use Contenir\Extension\Seo\Field\RobotsRulesField;
use Contenir\Extension\Seo\Field\WorkflowsField;
use Contenir\Extension\Seo\SeoExtension;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;

#[Group('unit')]
final class SeoExtensionTest extends TestCase
{
    private static function registration(): Registration
    {
        $registry = new Registry('seo');
        (new SeoExtension())->register($registry);

        return $registry->registration();
    }

    #[Test]
    public function declaresAFieldTypeForEachValueItChecks(): void
    {
        static::assertSame(
            [
                'seo:google-verification'    => GoogleTokenField::class,
                'seo:bing-verification'      => BingTokenField::class,
                'seo:pinterest-verification' => PinterestTokenField::class,
                'seo:meta-verification'      => MetaTokenField::class,
                'seo:workflows'              => WorkflowsField::class,
                'seo:robots-rules'           => RobotsRulesField::class,
            ],
            array_map(static fn(FieldTypeDeclaration $type): string => $type->class, self::registration()->fieldTypes),
        );
    }

    #[Test]
    public function declaresItsChecks(): void
    {
        static::assertSame(
            ['visibility' => VisibilityCheck::class, 'site-url' => SiteUrlCheck::class],
            array_map(
                static fn(DiagnosticDeclaration $check): string => $check->class,
                self::registration()->diagnostics,
            ),
        );
    }

    #[Test]
    public function declaresThreeSettingsSections(): void
    {
        static::assertSame(
            ['seo', 'seo-verification', 'seo-sitemap'],
            array_keys(self::registration()->settingsSections),
        );
    }

    #[Test]
    public function needsNoListenersHandlersTablesScriptsOrOtherAddOns(): void
    {
        $registration = self::registration();

        static::assertSame(
            [[], [], [], [], [], [], [], [], null],
            [
                $registration->listeners,
                $registration->handlers,
                $registration->webhooks,
                $registration->migrations,
                $registration->schedules,
                $registration->panels,
                $registration->dependencies,
                $registration->scripts,
                $registration->icons,
            ],
        );
    }
}

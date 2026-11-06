<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit;

use Contenir\Extension\Api\Diagnostic\DiagnosticDeclaration;
use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\Field\FieldTypeDeclaration;
use Contenir\Extension\Api\Listener\ListenerDeclaration;
use Contenir\Extension\Api\Panel\PanelDeclaration;
use Contenir\Extension\Api\Registration;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Seo\Check\DefaultsCheck;
use Contenir\Extension\Seo\Check\RecordsCheck;
use Contenir\Extension\Seo\Check\SectionsCheck;
use Contenir\Extension\Seo\Check\SitemapCheck;
use Contenir\Extension\Seo\Check\SiteUrlCheck;
use Contenir\Extension\Seo\Check\VisibilityCheck;
use Contenir\Extension\Seo\Field\AdvancedField;
use Contenir\Extension\Seo\Field\BingTokenField;
use Contenir\Extension\Seo\Field\GoogleTokenField;
use Contenir\Extension\Seo\Field\MetaTokenField;
use Contenir\Extension\Seo\Field\PinterestTokenField;
use Contenir\Extension\Seo\Field\RobotsRulesField;
use Contenir\Extension\Seo\Field\WorkflowsField;
use Contenir\Extension\Seo\Listener\CheckMeta;
use Contenir\Extension\Seo\Panel\SearchPanel;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Task\InspectRecords;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function dirname;

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
                'seo:advanced'               => AdvancedField::class,
            ],
            array_map(static fn(FieldTypeDeclaration $type): string => $type->class, self::registration()->fieldTypes),
        );
    }

    #[Test]
    public function declaresItsChecks(): void
    {
        static::assertSame(
            [
                'visibility' => VisibilityCheck::class,
                'site-url'   => SiteUrlCheck::class,
                'sections'   => SectionsCheck::class,
                'defaults'   => DefaultsCheck::class,
                'sitemap'    => SitemapCheck::class,
                'records'    => RecordsCheck::class,
            ],
            array_map(
                static fn(DiagnosticDeclaration $check): string => $check->class,
                self::registration()->diagnostics,
            ),
        );
    }

    #[Test]
    public function declaresItsRoundOfAskingTheSite(): void
    {
        $schedules = self::registration()->schedules;

        static::assertSame(
            [[InspectRecords::NAME], 15],
            [array_keys($schedules), $schedules[InspectRecords::NAME]->frequency->minutes ?? null],
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
    public function drawsItsAreaFromTheOverviewRecordsAndExportHandlers(): void
    {
        static::assertSame(
            [
                'overview',
                'records',
                'records-export',
                'preview',
                'records-check',
                'records-fix',
                'records-apply',
                'records-run',
                'records-undo',
            ],
            array_keys(self::registration()->handlers),
        );
    }

    #[Test]
    public function needsNoTablesScriptsOrOtherAddOns(): void
    {
        $registration = self::registration();

        static::assertSame(
            [[], [], [], [], null],
            [
                $registration->webhooks,
                $registration->migrations,
                $registration->dependencies,
                $registration->scripts,
                $registration->icons,
            ],
        );
    }

    #[Test]
    public function offersTheSearchAndSocialPanel(): void
    {
        static::assertSame(
            [SearchPanel::NAME => SearchPanel::class],
            array_map(static fn(PanelDeclaration $panel): string => $panel->class, self::registration()->panels),
        );
    }

    #[Test]
    public function readsSectionBlocksAndChecksTheRecordsTheyDeclareAsTheySave(): void
    {
        $registration = self::registration();

        static::assertSame(
            [
                SeoSectionBlock::class,
                [
                    [CheckMeta::NAME, RecordPreSave::class, CheckMeta::class, null],
                ],
            ],
            [
                $registration->sectionBlock,
                array_map(
                    static fn(ListenerDeclaration $listener): array => [
                        $listener->name,
                        $listener->event,
                        $listener->listener,
                        $listener->scope->fields,
                    ],
                    $registration->listeners,
                ),
            ],
        );
    }

    #[Test]
    public function shipsItsFieldsScriptFromItsPublicFolder(): void
    {
        $module = self::registration()->module;

        static::assertSame(
            [dirname(__DIR__, levels: 2) . '/public', AdvancedField::COMPONENT, AdvancedField::SCRIPT],
            [$module->assets, $module->components[0]->selector ?? null, $module->components[0]->script ?? null],
        );
    }
}

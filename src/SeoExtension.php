<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo;

use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\ExtensionInterface;
use Contenir\Extension\Api\Handler\Privilege;
use Contenir\Extension\Api\Listener\Scope;
use Contenir\Extension\Api\Registry;
use Contenir\Extension\Api\Schedule\Frequency;
use Contenir\Extension\Seo\Bulk\ApplyChanges;
use Contenir\Extension\Seo\Bulk\UndoChanges;
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
use Contenir\Extension\Seo\Module\ApplyHandler;
use Contenir\Extension\Seo\Module\CheckHandler;
use Contenir\Extension\Seo\Module\DashboardMeter;
use Contenir\Extension\Seo\Module\ExportHandler;
use Contenir\Extension\Seo\Module\FixHandler;
use Contenir\Extension\Seo\Module\OverviewHandler;
use Contenir\Extension\Seo\Module\PreviewHandler;
use Contenir\Extension\Seo\Module\RecordsHandler;
use Contenir\Extension\Seo\Module\RunHandler;
use Contenir\Extension\Seo\Module\StrategyHandler;
use Contenir\Extension\Seo\Module\StrategySaveHandler;
use Contenir\Extension\Seo\Module\UndoHandler;
use Contenir\Extension\Seo\Module\UseHandler;
use Contenir\Extension\Seo\Panel\SearchPanel;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Contenir\Extension\Seo\Section\SitemapSection;
use Contenir\Extension\Seo\Section\VerificationSection;
use Contenir\Extension\Seo\Task\InspectRecords;
use Override;

use function dirname;

/**
 * The SEO add-on: three settings sections (Search Appearance, Site
 * Verification and Sitemap), field types that check verification tokens,
 * workflow names and robots.txt rules as they are saved, the checks on the
 * dashboard, what a section's `<seo>` block binds, and the SEO area reporting
 * on its records.
 * Public sites read the settings through contenir/contenir-seo's adapters.
 *
 * @api
 */
final class SeoExtension implements ExtensionInterface
{
    #[Override]
    public function register(Registry $registry): void
    {
        $registry->fieldType(GoogleTokenField::NAME, GoogleTokenField::class);
        $registry->fieldType(BingTokenField::NAME, BingTokenField::class);
        $registry->fieldType(PinterestTokenField::NAME, PinterestTokenField::class);
        $registry->fieldType(MetaTokenField::NAME, MetaTokenField::class);
        $registry->fieldType(WorkflowsField::NAME, WorkflowsField::class);
        $registry->fieldType(RobotsRulesField::NAME, RobotsRulesField::class);
        $registry->fieldType(AdvancedField::NAME, AdvancedField::class);

        $registry->settingsSection(AppearanceSection::define());
        $registry->settingsSection(VerificationSection::define($registry->handle()));
        $registry->settingsSection(SitemapSection::define($registry->handle()));

        $registry->sectionBlock(SeoSectionBlock::class);
        $registry->listen(CheckMeta::NAME, RecordPreSave::class, CheckMeta::class, Scope::all());

        $registry->panel(SearchPanel::NAME, SearchPanel::class);

        $registry->diagnostic('visibility', VisibilityCheck::class);
        $registry->diagnostic('site-url', SiteUrlCheck::class);
        $registry->diagnostic('sections', SectionsCheck::class);
        $registry->diagnostic('defaults', DefaultsCheck::class);
        $registry->diagnostic('sitemap', SitemapCheck::class);
        $registry->diagnostic('records', RecordsCheck::class);

        $registry->module(dirname(__DIR__) . '/config/admin.xml');
        $registry->meter(DashboardMeter::class);
        $registry->task(ApplyChanges::NAME, ApplyChanges::class);
        $registry->task(UndoChanges::NAME, UndoChanges::class);
        $registry->templates(dirname(__DIR__) . '/templates');
        $registry->assets(dirname(__DIR__) . '/public');
        $registry->component(AdvancedField::COMPONENT, AdvancedField::SCRIPT);
        $registry->handler(OverviewHandler::NAME, OverviewHandler::class);
        $registry->handler(RecordsHandler::NAME, RecordsHandler::class);
        $registry->handler(StrategyHandler::NAME, StrategyHandler::class);
        $registry->handler(StrategySaveHandler::NAME, StrategySaveHandler::class, Privilege::Update);
        $registry->handler(ExportHandler::NAME, ExportHandler::class);
        $registry->handler(PreviewHandler::NAME, PreviewHandler::class);
        $registry->handler(CheckHandler::NAME, CheckHandler::class, Privilege::Update);
        $registry->handler(FixHandler::NAME, FixHandler::class, Privilege::Update);
        $registry->handler(ApplyHandler::NAME, ApplyHandler::class, Privilege::Update);
        $registry->handler(RunHandler::NAME, RunHandler::class);
        $registry->handler(UndoHandler::NAME, UndoHandler::class, Privilege::Update);
        $registry->handler(UseHandler::NAME, UseHandler::class, Privilege::Update);
        $registry->schedule(InspectRecords::NAME, InspectRecords::class, Frequency::everyMinutes(15));
    }
}

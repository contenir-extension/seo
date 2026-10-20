<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Seo\Check\VisibilityCheck;
use Contenir\Extension\Seo\Environment;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\SeoSettings;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The add-on on the API's in-memory host: its sections saved as the
 * settings screen saves them, and its checks and fix in the update check.
 */
#[Group('integration')]
final class InMemoryHostTest extends TestCase
{
    private static function host(string $environment): InMemoryHost
    {
        $http = new Psr17Factory();
        $host = new InMemoryHost($http, $http, new FrozenClock(new DateTimeImmutable('2026-10-21T09:00:00+00:00')));
        $host->boot(
            ['seo' => new SeoExtension()],
            ['seo'],
            [VisibilityCheck::class => new VisibilityCheck(new Environment($environment))],
        );

        return $host;
    }

    #[Test]
    public function readsSavedSectionsAsOneConfig(): void
    {
        $context = self::host('production')->context('seo');
        $context->settings('seo')->set(['title_position' => 'before', 'description' => 'Handmade furniture']);
        $context->settings('seo-verification')->set(['bing_site_verification' => 'b-token']);
        $context->settings('seo-sitemap')->set(['sitemap_exclude' => 'redirect']);

        $config = (new SeoSettings())->config($context);

        static::assertSame(
            ['before', 'Handmade furniture', ['bing' => 'b-token'], ['redirect'], true],
            [
                $config->title->position->value,
                $config->description,
                $config->verification,
                $config->sitemap->excluded,
                $config->sitemap->enabled,
            ],
        );
    }

    #[Test]
    public function showsAHiddenProductionSiteThroughItsFix(): void
    {
        $host = self::host('production');
        $host->context('seo')->settings('seo')->set(['visibility' => 'hide']);

        static::assertSame(
            [Status::Warning, Status::Success, 'auto'],
            [
                $host->check('seo', 'visibility')->status,
                $host->fix('seo', 'visibility', [], ['extension.seo.fix'])?->status,
                $host->context('seo')->settings('seo')->get('visibility'),
            ],
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Seo\SeoExtension;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The check on the sections' `<seo>` blocks, on the API's in-memory host.
 */
#[Group('integration')]
final class SectionsCheckTest extends TestCase
{
    private InMemoryHost $host;

    #[Test]
    public function aLinkedRecordsColumnNeedsNoFieldOfTheSection(): void
    {
        $this->host->linkable(new LinkableSection('library.projects.project', 'Projects'));
        $this->host->block(
            'library.projects.project',
            '<seo type="project"><image alt="assets[type=hero].alt, title">assets[type=hero].path, hero.path</image></seo>',
            ['title'],
        );

        static::assertEquals(
            CheckResult::success('1 section is described for search.'),
            $this->host->check('seo', 'sections'),
        );
    }

    #[Test]
    public function aMalformedLinkIsReportedAsAnUnknownField(): void
    {
        $this->host->linkable(new LinkableSection('library.projects.project', 'Projects'));
        $this->host->block(
            'library.projects.project',
            '<seo type="project"><image>assets[type].path</image></seo>',
            [],
        );

        static::assertEquals(
            CheckResult::warning(
                'Check the <seo> blocks in admin.xml: library.projects.project names assets[type].path, which it has no field for.',
            ),
            $this->host->check('seo', 'sections'),
        );
    }

    #[Test]
    public function aSiteWithoutBlocksSkipsTheCheck(): void
    {
        static::assertEquals(
            CheckResult::skipped('No section declares an <seo> block in admin.xml.'),
            $this->host->check('seo', 'sections'),
        );
    }

    #[Test]
    public function reportsEachBlockThatCannotBeUsed(): void
    {
        $this->host->linkable(new LinkableSection('library.projects.project', 'Projects'));
        $this->host->block(
            'library.projects.project',
            '<seo><title>meta_title, title</title><image alt="caption">image_lg</image></seo>',
            ['title', 'description'],
        );
        $this->host->block('library.press.article', '<seo type="news"/>', [
            'title',
            'meta_title',
            'meta_description',
            'description',
        ]);

        static::assertEquals(
            CheckResult::warning(
                'Check the <seo> blocks in admin.xml: library.projects.project names no type for the site to describe'
                    . ' it by; library.projects.project names meta_title, image_lg, caption, which it has no field for; library.press.article has no pages of its own to describe.',
            ),
            $this->host->check('seo', 'sections'),
        );
    }

    #[Test]
    public function wellDeclaredBlocksPass(): void
    {
        $this->host->linkable(new LinkableSection('library.projects.project', 'Projects'));
        $this->host->block('library.projects.project', '<seo type="project"/>', [
            'title',
            'meta_title',
            'meta_description',
            'description',
        ]);

        static::assertEquals(
            CheckResult::success('1 section is described for search.'),
            $this->host->check('seo', 'sections'),
        );
    }

    protected function setUp(): void
    {
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-05T09:00:00+00:00')),
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
    }
}

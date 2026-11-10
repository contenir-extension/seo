<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Report\SearchReport;
use Contenir\Extension\Seo\SeoExtension;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class SearchReportAppliesTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    private InMemoryHost $host;

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function blocks(): array
    {
        return [
            'only the title and description' => [
                '<seo type="project"/>',
                ['title', 'description'],
                ['title', 'meta_title', 'description'],
            ],
            'an image role'                  => [
                '<seo type="project"><image>image_lg</image></seo>',
                ['title', 'description', 'image'],
                ['title', 'description', 'image_lg'],
            ],
            'a discoverable role'            => [
                '<seo type="project"><discoverable>visible</discoverable></seo>',
                ['title', 'description', 'discoverable'],
                ['title', 'description', 'visible'],
            ],
            'a visible field and no role'    => [
                '<seo type="project"/>',
                ['title', 'description', 'discoverable'],
                ['title', 'description', 'visible'],
            ],
            'both'                           => [
                '<seo type="project"><image>image_lg</image><discoverable>visible</discoverable></seo>',
                ['title', 'description', 'image', 'discoverable'],
                ['title', 'description', 'image_lg', 'visible'],
            ],
        ];
    }

    #[Test]
    public function checksForHiddenRecordsOnceARecordIsHiddenWithoutASwitch(): void
    {
        $this->host->block(self::PROJECTS, '<seo type="project"><advanced>seo</advanced></seo>', ['title', 'seo']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'Privacy', 'seo' => '{"robots":"noindex"}']);

        static::assertSame(
            ['title', 'description', 'discoverable'],
            (new SearchReport($this->host->context('seo')))->sections()[0]->applies,
        );
    }

    /**
     * @param list<string> $applies
     * @param list<string> $fields
     */
    #[Test]
    #[DataProvider('blocks')]
    public function checksOnlyTheRolesTheBlockDeclares(string $xml, array $applies, array $fields): void
    {
        $this->host->block(self::PROJECTS, $xml, $fields);

        static::assertSame($applies, (new SearchReport($this->host->context('seo')))->sections()[0]->applies);
    }

    #[Test]
    public function doesNotCheckForHiddenRecordsWhereNoSwitchExistsAndNoRecordIsHidden(): void
    {
        $this->host->block(self::PROJECTS, '<seo type="project"><advanced>seo</advanced></seo>', ['title', 'seo']);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'House']);

        static::assertSame(
            ['title', 'description'],
            (new SearchReport($this->host->context('seo')))->sections()[0]->applies,
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-06T09:00:00+00:00')),
            new MapUrlResolver([]),
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
    }
}

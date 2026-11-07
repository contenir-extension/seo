<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Field\AdvancedField;
use Contenir\Extension\Seo\SeoExtension;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_encode;

#[Group('integration')]
final class AdvancedFieldSectionTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private InMemoryHost $host;

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function blocks(): array
    {
        return [
            'an automatic section'                => ['<seo type="project"/>', 'From content'],
            'a manual section'                    => ['<seo type="project" mode="manual"/>', 'Suggested from content'],
            'a description that takes no content' => ['<seo type="project"><description content="false"/></seo>', null],
        ];
    }

    #[Test]
    #[DataProvider('blocks')]
    public function notesADescriptionFromContentAsTheSectionsModeSays(string $block, ?string $note): void
    {
        $this->host->block(self::SECTION, $block, ['title', 'description', 'section']);
        $record = ['section' => (string) json_encode([
            [
                'type'   => 'text',
                'status' => '1',
                'fields' => ['description' => '<p>A weatherboard house in Northcote.</p>'],
            ],
        ])];

        $lines = (new AdvancedField())->displayIn(self::SECTION, null, $record, $this->host->context('seo'));

        static::assertSame($note, $lines[0]->note ?? null);
    }

    #[Test]
    public function readsAsAnAutomaticSectionWhereTheSectionHasNoBlock(): void
    {
        $record = ['section' => (string) json_encode([
            [
                'type'   => 'text',
                'status' => '1',
                'fields' => ['description' => '<p>A weatherboard house in Northcote.</p>'],
            ],
        ])];

        $lines = (new AdvancedField())->displayIn('library.unknown', null, $record, $this->host->context('seo'));

        static::assertSame('From content', $lines[0]->note ?? null);
    }

    protected function setUp(): void
    {
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-07T09:00:00+00:00')),
            new MapUrlResolver([]),
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
    }
}

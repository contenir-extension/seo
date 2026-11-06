<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Bulk\Run;
use Contenir\Extension\Seo\Bulk\RunChange;
use Contenir\Extension\Seo\Bulk\RunStore;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function array_map;
use function array_slice;
use function iterator_to_array;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mkdir;
use function strlen;
use function trim;

#[Group('integration')]
#[Group('bulk')]
final class BulkScreensTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    private const int NOW = 1_793_955_600;

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function appliesWhatTheReviewShowedAsAQueuedRun(): void
    {
        $response = $this->post('records-apply', [
            'fix'   => 'write-descriptions',
            'after' => [self::PROJECTS . '|1' => 'A weatherboard house, rewritten.'],
        ]);
        $this->host->runQueued();

        static::assertSame(
            [302, 'A weatherboard house, rewritten.', Run::FINISHED],
            [
                $response->getStatusCode(),
                $this->host->stored(self::PROJECTS, 1)['meta_description'] ?? null,
                $this->only()->state,
            ],
        );
    }

    #[Test]
    public function asksForNoColumnItWasNotGiven(): void
    {
        $this->post('records-apply', [
            'fix'   => 'hide',
            'after' => [self::PROJECTS . '|1' => 'anything', 'nope|9' => '0'],
        ]);

        static::assertSame(
            ['visible'],
            array_map(static fn(RunChange $change): string => $change->column, $this->only()->changes),
        );
    }

    #[Test]
    public function leavesOutADescriptionClearedInTheReview(): void
    {
        $response = $this->post('records-apply', [
            'fix'   => 'write-descriptions',
            'after' => [self::PROJECTS . '|1' => '  '],
        ]);

        static::assertSame([302, []], [$response->getStatusCode(), RunStore::at($this->directory)->all()]);
    }

    #[Test]
    public function offersTheChangesForReviewWithoutMakingThem(): void
    {
        $page = $this->page($this->post('records-fix', [
            'fix'     => 'hide',
            'records' => [self::PROJECTS . '|1'],
        ]));

        static::assertSame(
            [['House 1'], ['Visible in search', 'Yes', 'No'], 'Yes'],
            [
                $this->texts($page, '//tbody/tr[1]/td[1]/span[1]'),
                array_slice($this->cells($page), offset: 1),
                $this->host->stored(self::PROJECTS, 1)['visible'] ?? null ? 'Yes' : 'No',
            ],
        );
    }

    #[Test]
    public function putsBackTheFixFromItsScreen(): void
    {
        $this->post('records-apply', [
            'fix'   => 'write-descriptions',
            'after' => [self::PROJECTS . '|1' => 'Edited.'],
        ]);
        $this->host->runQueued();
        $run = $this->only();
        $this->post('records-undo', ['run' => $run->id]);
        $this->host->runQueued();

        static::assertSame([[RunChange::UNDONE], ''], [
            array_map(static fn(RunChange $change): string => $change->status, $this->only()->changes),
            $this->host->stored(self::PROJECTS, 1)['meta_description'] ?? null,
        ]);
    }

    #[Test]
    public function sendsThePersonBackToTheRunWhileItIsUndone(): void
    {
        $this->post('records-apply', [
            'fix'   => 'write-descriptions',
            'after' => [self::PROJECTS . '|1' => 'Edited.'],
        ]);
        $this->host->runQueued();
        $id       = $this->only()->id;
        $response = $this->post('records-undo', ['run' => $id]);

        static::assertSame("/admin/extension/seo/records-run?run={$id}", $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sendsThePersonToTheRunItMadeByItsId(): void
    {
        $response = $this->post('records-apply', [
            'fix'   => 'write-descriptions',
            'after' => [self::PROJECTS . '|1' => 'A weatherboard house.'],
        ]);
        $id = $this->only()->id;

        static::assertSame(
            ["/admin/extension/seo/records-run?run={$id}", 12],
            [$response->getHeaderLine('Location'), strlen($id)],
        );
    }

    #[Test]
    public function showsAPickBoxForEachRecordWhereTheHostCanFixThem(): void
    {
        $page = $this->page($this->get('records'));

        static::assertSame(
            [1, 'Fix all 1'],
            [
                $page->query('//input[@name="records[]"]')->length,
                $this->texts($page, '//button[contains(@class, "dropdown__trigger")]/span')[0] ?? null,
            ],
        );
    }

    #[Test]
    public function showsHowAFixWentWithAnUndoWhileItsValuesAreFresh(): void
    {
        $this->post('records-apply', [
            'fix'   => 'write-descriptions',
            'after' => [self::PROJECTS . '|1' => 'Edited.'],
        ]);
        $this->host->runQueued();
        $page = $this->page($this->get('records-run', ['run' => $this->only()->id]));

        static::assertSame(
            [['Saved'], 1],
            [
                $this->texts($page, '//tbody//span[contains(@class, "badge")]'),
                $page->query('//form[contains(@action, "records-undo")]')->length,
            ],
        );
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/data/cache", recursive: true);
        $this->writeConfig('config/autoload/settings.global.php', [
            'settings' => ['site' => ['url' => 'https://www.example.com', 'title' => 'Site']],
        ]);
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock((new DateTimeImmutable())->setTimestamp(self::NOW)),
            new MapUrlResolver([]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Site']);
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(
            self::PROJECTS,
            '<seo type="project"><discoverable>visible</discoverable></seo>',
            ['title', 'meta_title', 'meta_description', 'description', 'summary', 'visible'],
        );
        $this->host->seed(self::PROJECTS, 1, [
            'title'   => 'House 1',
            'summary' => 'A weatherboard house in Northcote, opened up to its garden.',
            'visible' => '1',
        ]);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @return list<string>
     */
    private function cells(DOMXPath $page): array
    {
        return array_map(
            static fn(DOMNode $cell): string => trim((string) $cell->textContent),
            iterator_to_array($page->query('//tbody/tr[1]/td'), preserve_keys: false),
        );
    }

    /**
     * @param array<string, string|int> $query
     */
    private function get(string $handler, array $query = []): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest('GET', "/admin/extension/seo/{$handler}")
            ->withQueryParams($query);

        return $this->host->handle($request, ['seo', self::PROJECTS], new Editor(7, 'Ann Editor'));
    }

    private function only(): Run
    {
        $runs = RunStore::at($this->directory)->all();
        static::assertCount(1, $runs);

        return $runs[0];
    }

    private function page(ResponseInterface $response): DOMXPath
    {
        $document = new DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML("<?xml encoding=\"UTF-8\">{$response->getBody()}");
        libxml_clear_errors();

        return new DOMXPath($document);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function post(string $handler, array $body): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest('POST', "/admin/extension/seo/{$handler}")
            ->withParsedBody($body);

        return $this->host->handle($request, ['seo', self::PROJECTS], new Editor(7, 'Ann Editor'));
    }

    /**
     * @return list<string>
     */
    private function texts(DOMXPath $page, string $query): array
    {
        return array_map(
            static fn(DOMNode $node): string => trim((string) $node->textContent),
            iterator_to_array($page->query($query), preserve_keys: false),
        );
    }
}

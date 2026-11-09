<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Strategy\StrategyOverrides;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
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
use function trim;

#[Group('integration')]
final class StrategyScreensTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    use TempDirectoryTrait;

    private InMemoryHost $host;

    #[Test]
    public function keepsTheSchemaTypeUnderAdvanced(): void
    {
        static::assertSame(
            ['Advanced'],
            $this->texts($this->page($this->get(['section' => self::PROJECTS])), '//details/summary'),
        );
    }

    #[Test]
    public function leavesTheSitesOwnAdminXmlAsItWas(): void
    {
        $this->post(['section' => self::PROJECTS, 'version' => $this->version(), 'schema' => 'Article']);

        static::assertSame(
            'CreativeWork',
            $this
                ->host->context('seo')->sections()->block(self::PROJECTS)
                ?->element->attribute('schema'),
        );
    }

    #[Test]
    public function listsEachSectionWithItsSchemaTypeAndWhereItsRolesAreRead(): void
    {
        $page = $this->page($this->get());

        static::assertSame(
            [
                'Projects',
                'Creative work',
                'Meta title, Title',
                'Meta description, Description, From content',
                'From content',
                'Automatic',
            ],
            array_slice($this->texts($page, '//tbody/tr[1]/td'), offset: 0, length: 6),
        );
    }

    #[Test]
    public function marksASectionChangedHereOnceItIs(): void
    {
        $this->post(['section' => self::PROJECTS, 'version' => $this->version(), 'schema' => 'Article']);

        static::assertSame(
            ['Changed here'],
            $this->texts($this->page($this->get()), '//span[@class="badge badge--secondary"]'),
        );
    }

    #[Test]
    public function marksNoSectionChangedHereBeforeAnyIs(): void
    {
        static::assertSame([], $this->texts($this->page($this->get()), '//span[@class="badge badge--secondary"]'));
    }

    #[Test]
    public function offersTheSitesDefaultsOnlyForASectionThatWasChanged(): void
    {
        $before = $this->texts($this->page($this->get(['section' => self::PROJECTS])), '//button[@name="reset"]');
        $this->post(['section' => self::PROJECTS, 'version' => $this->version(), 'schema' => 'Article']);

        static::assertSame(
            [[], ["Use the site's defaults"]],
            [$before, $this->texts($this->page($this->get(['section' => self::PROJECTS])), '//button[@name="reset"]')],
        );
    }

    #[Test]
    public function opensASectionToChangeItsStrategy(): void
    {
        $page = $this->page($this->get(['section' => self::PROJECTS]));

        static::assertSame(
            ['CreativeWork'],
            array_map(
                static fn(DOMNode $input): string => $input instanceof DOMElement ? $input->getAttribute('value') : '',
                iterator_to_array($page->query('//input[@name="schema"]'), preserve_keys: false),
            ),
        );
    }

    #[Test]
    public function opensTheSectionAskedForNotTheFirst(): void
    {
        $page = $this->page($this->get(['section' => 'library.news.article']));

        static::assertSame(
            ['NewsArticle'],
            array_map(
                static fn(DOMNode $input): string => $input instanceof DOMElement ? $input->getAttribute('value') : '',
                iterator_to_array($page->query('//input[@name="schema"]'), preserve_keys: false),
            ),
        );
    }

    #[Test]
    public function refusesASaveMadeAgainstAVersionThatHasMovedOn(): void
    {
        $before = $this->version();
        $this->post(['section' => self::PROJECTS, 'version' => 'stale', 'schema' => 'Article']);

        static::assertSame($before, $this->version());
    }

    #[Test]
    public function refusesASaveToARoleThatMayOnlyOpenTheStrategyScreen(): void
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/strategy-save')
            ->withParsedBody(['section' => self::PROJECTS, 'version' => '']);

        static::assertSame(
            403,
            $this->host
                ->handle($request, ['seo', 'seo.strategy.screen', self::PROJECTS], new Editor(7, 'Ann Editor'))
                ->getStatusCode(),
        );
    }

    #[Test]
    public function refusesASourceThatIsNotAFieldOfTheSectionAndChangesNothing(): void
    {
        $before = $this->version();
        $this->post([
            'section' => self::PROJECTS,
            'version' => $before,
            'sources' => ['title' => ['nope']],
        ]);

        static::assertSame($before, $this->version());
    }

    #[Test]
    public function refusesTheStrategyScreenToARoleGrantedOnlyTheSeoArea(): void
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/strategy');

        static::assertSame(
            403,
            $this->host->handle($request, ['seo', self::PROJECTS], new Editor(7, 'Ann Editor'))->getStatusCode(),
        );
    }

    #[Test]
    public function returnsASectionToTheSitesDefaultsOnRequest(): void
    {
        $this->post(['section' => self::PROJECTS, 'version' => $this->version(), 'schema' => 'Article']);
        $this->post(['section' => self::PROJECTS, 'version' => $this->version(), 'reset' => '1']);

        static::assertSame(
            ['CreativeWork', false],
            [
                SeoBlocks::of($this->host->context('seo'))->one(self::PROJECTS)?->element->attribute('schema'),
                StrategyOverrides::at($this->directory)->customised(self::PROJECTS),
            ],
        );
    }

    #[Test]
    public function savesWhatTheFormSaysIntoTheSectionsBlock(): void
    {
        $response = $this->post([
            'section' => self::PROJECTS,
            'version' => $this->version(),
            'schema'  => 'Article',
            'mode'    => 'manual',
            'sources' => ['title' => ['title'], 'description' => ['summary', 'description'], 'image' => ['image_lg']],
            'content' => ['description' => '1'],
        ]);
        $block = SeoBlocks::of($this->host->context('seo'))->one(self::PROJECTS)?->element;

        static::assertSame(
            [302, 'Article', 'manual', ['title', 'summary, description', 'image_lg']],
            [
                $response->getStatusCode(),
                $block?->attribute('schema'),
                $block?->attribute('mode'),
                array_map(static fn($child): string => $child->text, $block->children ?? []),
            ],
        );
    }

    #[Test]
    public function sendsASaveOfNoSectionBackToTheList(): void
    {
        static::assertSame(302, $this->post(['section' => 'library.nope.nope', 'version' => ''])->getStatusCode());
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/data/cache", recursive: true);
        $http       = new Psr17Factory();
        $this->host = new InMemoryHost(
            $http,
            $http,
            new FrozenClock(new DateTimeImmutable('2026-11-07 09:00:00')),
            new MapUrlResolver([]),
            $this->directory,
        );
        $this->host->boot(['seo' => new SeoExtension()], ['seo']);
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(
            self::PROJECTS,
            '<seo type="project" schema="CreativeWork"/>',
            ['title', 'meta_title', 'meta_description', 'description', 'summary', 'image_lg'],
        );
        $this->host->block('library.news.article', '<seo type="news" schema="NewsArticle"/>', ['title']);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    /**
     * @param array<string, string> $query
     */
    private function get(array $query = []): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/strategy')
            ->withQueryParams($query);

        return $this->host->handle(
            $request,
            ['seo', 'seo.strategy.screen', 'seo.strategy.save', self::PROJECTS],
            new Editor(7, 'Ann Editor'),
        );
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
    private function post(array $body): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/strategy-save')
            ->withParsedBody($body);

        return $this->host->handle(
            $request,
            ['seo', 'seo.strategy.screen', 'seo.strategy.save', self::PROJECTS],
            new Editor(7, 'Ann Editor'),
        );
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

    private function version(): string
    {
        return StrategyOverrides::at($this->directory)->version();
    }
}

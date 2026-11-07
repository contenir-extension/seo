<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Editor\Editor;
use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ResultKind;
use Contenir\Extension\Api\Record\LinkableSection;
use Contenir\Extension\Api\Record\RecordUpdatesInterface;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Api\Testing\MapUrlResolver;
use Contenir\Extension\Seo\Module\UseHandler;
use Contenir\Extension\Seo\SeoExtension;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RefusingUpdates;
use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

use function json_encode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;

/**
 * Saving the description a record's content gives as the record's own, one record at a time.
 */
#[Group('integration')]
final class UseDescriptionTest extends TestCase
{
    private const string PROJECTS = 'library.projects.project';

    private const string WORDS = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    private const string BACK = '/admin/records/library.projects.project/1';

    private InMemoryHost $host;

    /**
     * @return array<string, array{ActionResult, string}>
     */
    public static function refusalProvider(): array
    {
        return [
            'held with a reason' => [
                new ActionResult(Outcome::Stopped, 1, 'Held.', errors: ['meta_description' => ['Too long.']]),
                'Too long.',
            ],
            'held without one'   => [
                new ActionResult(Outcome::Stopped, 1),
                'The description could not be saved.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('refusalProvider')]
    public function leavesTheRecordAloneWhenTheHostHoldsTheSave(ActionResult $result, string $reason): void
    {
        $this->host->boot([], [], [RecordUpdatesInterface::class => new RefusingUpdates($result)]);
        $response = $this->post(['section' => self::PROJECTS, 'id' => '1', 'after' => self::WORDS]);

        $result = $this->handle(['section' => self::PROJECTS, 'id' => '1', 'after' => self::WORDS]);

        static::assertSame(
            [self::BACK, null, [['level' => 'danger', 'text' => $reason]]],
            [
                $response->getHeaderLine('Location'),
                $this->host->stored(self::PROJECTS, 1)['meta_description'] ?? null,
                $result->messages,
            ],
        );
    }

    #[Test]
    public function postsBackToItselfWithTheRecordItWasOpenedFor(): void
    {
        $page = $this->page($this->get(['section' => self::PROJECTS, 'id' => '1']));
        $form = $page->query('//form')->item(0);

        static::assertSame(
            ['post', '/admin/extension/seo/use', 1],
            [
                $form instanceof DOMElement ? $form->getAttribute('method') : null,
                $form instanceof DOMElement ? $form->getAttribute('action') : null,
                $page->query('//input[@name="id"][@value="1"]')->length,
            ],
        );
    }

    #[Test]
    public function readsTheRecordFromTheQueryWhenOnlyShown(): void
    {
        static::assertSame(
            ResultKind::Template,
            (new UseHandler())->handle(
                (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/use')
                    ->withQueryParams(['section' => self::PROJECTS, 'id' => '1']),
                $this->host->context('seo'),
            )->kind,
        );
    }

    #[Test]
    public function saysTheDescriptionWasSaved(): void
    {
        static::assertSame(
            [['level' => 'success', 'text' => 'Saved as the record\'s own description.']],
            $this->handle(['section' => self::PROJECTS, 'id' => '1', 'after' => self::WORDS])->messages,
        );
    }

    #[Test]
    public function saysWhenARequestNamesNoRecord(): void
    {
        static::assertSame(
            [['level' => 'warning', 'text' => 'That record\'s description can\'t be written here.']],
            $this->handle(['section' => self::PROJECTS])->messages,
        );
    }

    #[Test]
    public function saysWhenTheRecordHasNothingToWrite(): void
    {
        static::assertSame(
            [[
                'level' => 'warning',
                'text'  => 'Nothing to write: the record has a description already, or its content gives none.',
            ]],
            $this->handle(['section' => self::PROJECTS, 'id' => '2', 'after' => self::WORDS])->messages,
        );
    }

    #[Test]
    public function saysWhyNothingWasWrittenWhenTheContentChanged(): void
    {
        static::assertSame(
            [[
                'level' => 'warning',
                'text'  => 'The record\'s content changed since this was opened. Check the text again.',
            ]],
            $this->handle(['section' => self::PROJECTS, 'id' => '1', 'after' => 'Older.'])->messages,
        );
    }

    #[Test]
    public function sendsARecordTheHostCannotDescribeToTheRecordsScreen(): void
    {
        $response = $this->post(['section' => self::PROJECTS, 'id' => '99', 'after' => self::WORDS]);

        static::assertSame('/admin/extension/seo/records', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function sendsARequestForNoRecordToTheRecordsScreen(): void
    {
        static::assertSame(
            '/admin/extension/seo/records',
            $this->get(['section' => self::PROJECTS])->getHeaderLine('Location'),
        );
    }

    #[Test]
    public function sendsARequestForNoSectionToTheRecordsScreen(): void
    {
        static::assertSame('/admin/extension/seo/records', $this->get(['id' => '1'])->getHeaderLine('Location'));
    }

    #[Test]
    public function showsTheTextThatWouldBeWrittenAndWritesNothing(): void
    {
        $response = $this->get(['section' => self::PROJECTS, 'id' => '1']);
        $page     = $this->page($response);
        $after    = $page->query('//input[@name="after"]')->item(0);

        static::assertSame(
            [self::WORDS, null],
            [
                $after instanceof DOMElement ? $after->getAttribute('value') : null,
                $this->host->stored(self::PROJECTS, 1)['meta_description'] ?? null,
            ],
        );
    }

    #[Test]
    public function writesNothingForARecordThatHasADescriptionAlready(): void
    {
        $response = $this->post(['section' => self::PROJECTS, 'id' => '2', 'after' => self::WORDS]);

        static::assertSame(
            [302, 'Its own.'],
            [$response->getStatusCode(), $this->host->stored(self::PROJECTS, 2)['meta_description'] ?? null],
        );
    }

    #[Test]
    public function writesNothingWhenTheContentChangedSinceThePageWasOpened(): void
    {
        $response = $this->post(['section' => self::PROJECTS, 'id' => '1', 'after' => 'Older words that were shown.']);

        static::assertSame(
            ['/admin/extension/seo/use?section=library.projects.project&id=1', null],
            [$response->getHeaderLine('Location'), $this->host->stored(self::PROJECTS, 1)['meta_description'] ?? null],
        );
    }

    #[Test]
    public function writesTheTextShownIntoTheRecordsOwnDescriptionAndReturnsToTheRecord(): void
    {
        $response = $this->post(['section' => self::PROJECTS, 'id' => '1', 'after' => self::WORDS]);

        static::assertSame(
            [302, self::BACK, self::WORDS],
            [
                $response->getStatusCode(),
                $response->getHeaderLine('Location'),
                $this->host->stored(self::PROJECTS, 1)['meta_description'] ?? null,
            ],
        );
    }

    #[Override]
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
        $this->host->linkable(new LinkableSection(self::PROJECTS, 'Project'));
        $this->host->block(
            self::PROJECTS,
            '<seo type="project"/>',
            ['title', 'meta_title', 'meta_description', 'description', 'summary', 'visible', 'section'],
        );
        $content = (string) json_encode([
            ['type' => 'text', 'status' => '1', 'fields' => ['description' => '<p>' . self::WORDS . '</p>']],
        ]);
        $this->host->seed(self::PROJECTS, 1, ['title' => 'House', 'section' => $content]);
        $this->host->seed(self::PROJECTS, 2, [
            'title'            => 'Cottage',
            'meta_description' => 'Its own.',
            'section'          => $content,
        ]);
    }

    /**
     * @param array<string, string> $query
     */
    private function get(array $query): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest('GET', '/admin/extension/seo/use')
            ->withQueryParams($query);

        return $this->host->handle($request, ['seo', self::PROJECTS], new Editor(7, 'Ann Editor'));
    }

    /**
     * @param array<string, string> $body
     */
    private function handle(array $body): HandlerResult
    {
        return (new UseHandler())->handle(
            (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/use')
                ->withParsedBody($body),
            $this->host->context('seo'),
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
     * @param array<string, string> $body
     */
    private function post(array $body): ResponseInterface
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/admin/extension/seo/use')
            ->withParsedBody($body);

        return $this->host->handle($request, ['seo', self::PROJECTS], new Editor(7, 'Ann Editor'));
    }
}

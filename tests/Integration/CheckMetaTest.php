<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Testing\FrozenClock;
use Contenir\Extension\Api\Testing\InMemoryHost;
use Contenir\Extension\Seo\SeoExtension;
use DateTimeImmutable;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * The save check a section's `<seo>` block binds, on the API's in-memory
 * host: what it tells an editor saving a record, and when it holds the save.
 */
#[Group('integration')]
final class CheckMetaTest extends TestCase
{
    private const string SECTION = 'library.projects.project';

    private const string BLOCK =
        '<seo type="project">'
            . '<description required="true">meta_description, description</description>'
            . '<image>image_lg</image>'
            . '</seo>';

    private const array FIELDS = ['title', 'meta_title', 'meta_description', 'description', 'image_lg'];

    private const string DESCRIPTION = 'A weatherboard house in Northcote, opened up to its garden with a new kitchen and a deck.';

    private InMemoryHost $host;

    /**
     * @return array<string, array{array<string, string>, array<string, list<string>>}>
     */
    public static function warningProvider(): array
    {
        $complete = ['title' => 'House', 'description' => self::DESCRIPTION, 'image_lg' => '/asset/house.jpg'];

        return [
            'nothing missing'             => [$complete, []],
            'no title'                    => [
                [...$complete, 'title' => ''],
                ['meta_title' => ['Add a title for search results.']],
            ],
            'a title too long'            => [
                [...$complete, 'title' => str_repeat('a', times: 45)],
                ['meta_title' => [
                    'With the site name, the title is 61 characters; search results cut it off after about 60.',
                ]],
            ],
            'a meta title over the title' => [[...$complete, 'meta_title' => 'House in Northcote'], []],
            'a short description'         => [
                [...$complete, 'description' => 'A house.'],
                ['meta_description' => ['The description is 8 characters; search results have room for about 160.']],
            ],
            'a long description'          => [
                [...$complete, 'description' => str_repeat('a', times: 161)],
                ['meta_description' => [
                    'The description is 161 characters; search results cut it off after about 160.',
                ]],
            ],
            'no image'                    => [
                [...$complete, 'image_lg' => ' '],
                ['image_lg' => ['Add an image for shared links.']],
            ],
        ];
    }

    #[Test]
    public function aNewRecordIsCheckedOnce(): void
    {
        static::assertSame(
            ['image_lg' => ['Add an image for shared links.']],
            $this->host->save(self::SECTION, null, ['title' => 'House', 'description' => self::DESCRIPTION])->warnings,
        );
    }

    #[Test]
    public function aRequiredRoleLeftEmptyHoldsTheSave(): void
    {
        $result = $this->save(['title' => 'House', 'image_lg' => '/asset/house.jpg']);

        static::assertSame(
            [Outcome::Stopped, ['meta_description' => ['Add a description for search results and shared links.']]],
            [$result->outcome, $result->errors],
        );
    }

    #[Test]
    public function aSaveThatLeavesTheSearchColumnsAloneIsNotChecked(): void
    {
        $this->host->seed(self::SECTION, 1, ['title' => '', 'notes' => 'Old']);

        static::assertSame([], $this->save(['notes' => 'New'])->warnings);
    }

    #[Test]
    public function aSectionWithoutABlockIsNotChecked(): void
    {
        static::assertSame([], $this->host->save('site.page.resource', 1, ['title' => ''])->warnings);
    }

    #[Test]
    public function aTitleAnotherRecordHasIsNoted(): void
    {
        $this->host->seed(self::SECTION, 2, ['title' => ' house ']);

        static::assertSame(
            ['meta_title' => [
                'Another record in this section has the same title, so search results can\'t tell them apart.',
            ]],
            $this->save(['title' => 'House', 'description' => self::DESCRIPTION, 'image_lg' => '/asset/house.jpg'])
                ->warnings,
        );
    }

    /**
     * @param array<string, string> $values
     * @param array<string, list<string>> $warnings
     */
    #[Test]
    #[DataProvider('warningProvider')]
    public function tellsTheEditorWhatSearchResultsWillMakeOfTheRecord(array $values, array $warnings): void
    {
        $result = $this->save($values);

        static::assertSame([Outcome::Done, $warnings], [$result->outcome, $result->warnings]);
    }

    #[Test]
    public function theRecordsOwnTitleIsNoDuplicate(): void
    {
        $this->host->seed(self::SECTION, 1, ['title' => 'House']);

        static::assertSame(
            [],
            $this->save(['title' => 'House', 'description' => self::DESCRIPTION, 'image_lg' => '/asset/house.jpg'])
                ->warnings,
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
        $this->host->block(self::SECTION, self::BLOCK, self::FIELDS);
        $this->host->context('seo')->settings('seo')->set(['site_name' => 'Studio Esteta', 'title_separator' => '|']);
    }

    /**
     * @param array<string, string> $values
     */
    private function save(array $values): ActionResult
    {
        return $this->host->save(self::SECTION, 1, $values);
    }
}

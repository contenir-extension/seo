<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Integration;

use Contenir\Extension\Seo\Bulk\RunStore;
use Contenir\Extension\Seo\Tests\TestAsset\Bulk\RunFactory;
use Contenir\Extension\Seo\Tests\Trait\TempDirectoryTrait;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function mkdir;

#[Group('integration')]
#[Group('bulk')]
final class RunStoreTest extends TestCase
{
    use TempDirectoryTrait;

    #[Test]
    public function findsARunByItsId(): void
    {
        $store = $this->store();
        $store->put(RunFactory::make('run-1'));
        $store->put(RunFactory::make('run-2', at: 2_000));

        static::assertSame('run-2', $store->find('run-2')?->id);
    }

    #[Test]
    public function findsNothingForARunItNeverKept(): void
    {
        static::assertNull($this->store()->find('run-9'));
    }

    #[Test]
    public function keepsNothingWithoutItsFolder(): void
    {
        $store = new RunStore("{$this->directory}/nowhere/runs.json");
        $store->put(RunFactory::make());

        static::assertSame([], $store->all());
    }

    #[Test]
    public function keepsOnlyTheMostRecentRuns(): void
    {
        $store = $this->store();
        for ($number = 1; $number <= (RunStore::KEPT + 3); ++$number) {
            $store->put(RunFactory::make("run-{$number}", at: $number));
        }

        static::assertSame(
            [RunStore::KEPT, 'run-' . (RunStore::KEPT + 3), 'run-4'],
            [count($store->all()), $store->all()[0]->id, $store->all()[RunStore::KEPT - 1]->id],
        );
    }

    #[Test]
    public function listsTheNewestFirst(): void
    {
        $store = $this->store();
        $store->put(RunFactory::make('old', at: 1_000));
        $store->put(RunFactory::make('new', at: 3_000));
        $store->put(RunFactory::make('mid', at: 2_000));

        static::assertSame(['new', 'mid', 'old'], array_map(static fn($run): string => $run->id, $store->all()));
    }

    #[Test]
    public function replacesARunItAlreadyKeeps(): void
    {
        $store = $this->store();
        $store->put(RunFactory::make('run-1', state: 'queued'));
        $store->put(RunFactory::make('run-1', state: 'finished'));

        static::assertSame(['finished'], array_map(static fn($run): string => $run->state, $store->all()));
    }

    #[Override]
    protected function setUp(): void
    {
        $this->makeTempDirectory();
        mkdir("{$this->directory}/data/cache", recursive: true);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeTempDirectory();
    }

    private function store(): RunStore
    {
        return RunStore::at($this->directory);
    }
}

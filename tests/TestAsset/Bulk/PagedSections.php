<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use LogicException;
use Override;

use function array_map;
use function min;
use function range;

/**
 * Sections with a fixed number of records, each titled "House n", read a page at a time. It fails the test
 * when it is asked for more pages than it should be, so a loop that never ends fails fast.
 */
final class PagedSections implements SectionsInterface
{
    use SavesNoBlocksTrait;

    public int $calls = 0;

    /**
     * @param int $maxCalls How many times it may be asked for a page before it refuses
     */
    public function __construct(
        private readonly int $total,
        private readonly int $maxCalls = 80,
    ) {}

    #[Override]
    public function block(string $section): ?SectionBlock
    {
        return null;
    }

    #[Override]
    public function blocks(): array
    {
        return [];
    }

    #[Override]
    public function rows(string $section, array $columns, array $ids = [], int $limit = 100, int $offset = 0): array
    {
        if (++$this->calls > $this->maxCalls) {
            throw new LogicException('The sections were asked for too many pages');
        }

        $numbers = $this->total > $offset ? range($offset + 1, min($this->total, $offset + $limit)) : [];

        return array_map(
            static fn(int $number): RecordRow => new RecordRow(
                new RecordReference($section, $number),
                ($number % 10) === 0 ? RecordStatus::Draft : RecordStatus::Published,
                ['title' => "House {$number}"],
            ),
            $numbers,
        );
    }
}

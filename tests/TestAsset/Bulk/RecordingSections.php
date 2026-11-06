<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Record\RecordStatus;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Extension\Api\Url\RecordReference;
use Override;

use function array_map;

/**
 * Sections that hold the same values for every record and note what each call asked for.
 */
final class RecordingSections implements SectionsInterface
{
    use SavesNoBlocksTrait;

    /** @var list<array{string, list<string>, list<string|int>}> */
    public array $asked = [];

    /**
     * @param array<string, scalar|null>  $held   What every record holds, by column
     * @param array<string, SectionBlock> $blocks The sections that have a block, by section
     */
    public function __construct(
        private readonly array $held = [],
        private readonly array $blocks = [],
    ) {}

    #[Override]
    public function block(string $section): ?SectionBlock
    {
        return $this->blocks[$section] ?? null;
    }

    #[Override]
    public function blocks(): array
    {
        return [];
    }

    #[Override]
    public function rows(string $section, array $columns, array $ids = [], int $limit = 100, int $offset = 0): array
    {
        $this->asked[] = [$section, $columns, $ids];

        return array_map(
            fn(string|int $id): RecordRow => new RecordRow(
                new RecordReference($section, $id),
                RecordStatus::Published,
                $this->held,
            ),
            $ids,
        );
    }
}

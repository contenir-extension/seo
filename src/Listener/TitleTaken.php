<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Section\SectionsInterface;
use Contenir\Seo\Sections\Overrides;

use function array_diff;
use function count;
use function is_scalar;
use function mb_strtolower;
use function trim;

/**
 * Whether another record of a section already has a title: its first title
 * column that holds text, compared without case or surrounding space. The
 * section is read a page at a time, up to MAX_ROWS records.
 */
final readonly class TitleTaken
{
    public const int PAGE = 100;

    /** The most records read to find a duplicate, so a save never waits on a very long section. */
    public const int MAX_ROWS = 5000;

    public const string MESSAGE = 'Another record in this section has the same title, so search results can\'t tell them apart.';

    public function __construct(
        private SectionsInterface $sections,
    ) {}

    /**
     * @param array<string, scalar|null> $values
     */
    private static function kept(array $values, ?string $advanced): ?string
    {
        return null === $advanced ? null : Overrides::from($values[$advanced] ?? null)['title'] ?? null;
    }

    /**
     * @param array<string, scalar|null> $values
     * @param list<string> $columns
     */
    private static function title(array $values, array $columns, ?string $advanced): ?string
    {
        $kept = self::kept($values, $advanced);
        if (null !== $kept) {
            return mb_strtolower($kept);
        }

        foreach (array_diff($columns, [$advanced]) as $column) {
            $value = $values[$column] ?? null;
            $text  = is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
            if ('' !== $text) {
                return $text;
            }
        }

        return null;
    }

    /**
     * A note for the record when another has its title, or none.
     *
     * @param list<string> $columns The section's title columns it has, first non-empty first
     *
     * @throws RowsRefusedException When the section has no block, or a column isn't one the block reads.
     */
    public function note(
        string $section,
        array $columns,
        string $title,
        string|int|null $id,
        ?string $advanced = null,
    ): ?string {
        return [] !== $columns && $this->taken($section, $columns, $title, $id, $advanced)
            ? self::MESSAGE
            : null;
    }

    /**
     * @param list<string> $columns
     *
     * @throws RowsRefusedException
     */
    private function taken(
        string $section,
        array $columns,
        string $title,
        string|int|null $id,
        ?string $advanced,
    ): bool {
        $wanted = mb_strtolower(trim($title));
        for ($offset = 0; $offset < self::MAX_ROWS; $offset += self::PAGE) {
            $rows = $this->sections->rows($section, $columns, limit: self::PAGE, offset: $offset);
            foreach ($rows as $row) {
                if (
                    (string) $row->record->id !== (string) $id
                    && $wanted === self::title($row->values, $columns, $advanced)
                ) {
                    return true;
                }
            }

            if (count($rows) < self::PAGE) {
                return false;
            }
        }

        return false;
    }
}

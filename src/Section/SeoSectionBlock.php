<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Extension\Api\Event\Record\RecordPreSave;
use Contenir\Extension\Api\Section\ImpliedListener;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Api\Section\SectionBlockInterface;
use Contenir\Extension\Seo\Listener\CheckMeta;
use Contenir\Seo\Sections\ContentBlocks;
use Override;

use function array_merge;
use function in_array;
use function preg_match;

/**
 * What a section's `<seo>` block asks of the host: the columns the add-on
 * reads, and the save check, bound to the section's title, description and
 * image columns, for new and changed records alike, as a new record's save
 * fires the pre-save event too. A column the section doesn't have is left
 * out here, and reported by the sections check.
 */
final readonly class SeoSectionBlock implements SectionBlockInterface
{
    /**
     * The roles the save check reads.
     *
     * @var list<string>
     */
    public const array CHECKED = ['title', 'description', 'image'];

    /**
     * Columns a section may keep a summary in, which a bulk fix writes descriptions from.
     *
     * @var list<string>
     */
    public const array SUMMARY = ['summary', 'abstract', 'excerpt', 'intro', 'lead', 'teaser'];

    /**
     * The columns that are fields of the section, by a name the host accepts, each once.
     *
     * @param list<string> $columns
     *
     * @return list<string>
     */
    public static function known(SectionBlock $block, array $columns): array
    {
        $known = [];
        foreach ($columns as $column) {
            if (
                ! (
                    $block->hasField($column)
                    && 1 === preg_match('/^[a-z][a-z0-9_]{0,63}$/', $column)
                    && ! in_array($column, $known, strict: true)
                )
            ) {
                continue;
            }

            $known[] = $column;
        }

        return $known;
    }

    /**
     * @return list<string>
     */
    #[Override]
    public function columns(SectionBlock $block): array
    {
        return self::known($block, [
            ...SeoDeclaration::read($block->element)->columns(),
            ...self::SUMMARY,
            ContentBlocks::COLUMN,
        ]);
    }

    /**
     * @return list<ImpliedListener>
     */
    #[Override]
    public function listeners(SectionBlock $block): array
    {
        $seo   = SeoDeclaration::read($block->element);
        $parts = [];
        foreach (self::CHECKED as $role) {
            $parts[] = $seo->roles[$role] ?? [];
        }

        /** @var list<string> $columns */
        $columns = array_merge(...$parts, ...[null === $seo->advanced ? [] : [$seo->advanced]]);
        $fields  = self::known($block, $columns);

        return [
            new ImpliedListener(CheckMeta::NAME, RecordPreSave::class, $fields),
        ];
    }
}

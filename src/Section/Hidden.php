<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Section;

use Contenir\Seo\Sections\Overrides;
use Contenir\Seo\Sections\SeoBlock;

use function in_array;
use function is_string;
use function trim;

/**
 * Whether search engines are kept from a record's page, and why: its Discoverable switch is off, its own
 * robots setting keeps them out, or the page the site rendered for it told them so. The switch is the first
 * column of the block's `discoverable` role, or `visible` where the block names none. The report, the
 * record's panel, the save check and the bulk fixes all ask here, so they never disagree.
 */
final class Hidden
{
    /** The column a section keeps its Discoverable switch in when its block names none. */
    public const string COLUMN = 'visible';

    public const string BY_SWITCH = 'Hidden from search: its Discoverable switch is off.';

    public const string BY_ROBOTS = 'Hidden from search: its own robots setting keeps search engines out.';

    public const string BY_PAGE = 'Hidden from search: its page tells search engines not to index it.';

    /** What a switch's column holds when it is off, text trimmed; a record that holds nothing in it is shown. */
    private const array OFF = ['0', 0, false, ''];

    /**
     * The column of the section's Discoverable switch.
     */
    public static function column(SeoBlock $seo): string
    {
        return $seo->roles['discoverable'][0] ?? self::COLUMN;
    }

    /**
     * Whether the record's switch is off.
     *
     * @param array<string, mixed> $values The record's columns by name
     */
    public static function off(SeoBlock $seo, array $values): bool
    {
        /** @var mixed $held */
        $held = $values[self::column($seo)] ?? null;

        return in_array(is_string($held) ? trim($held) : $held, self::OFF, strict: true);
    }

    /**
     * Why the record is hidden from search, as the editor is told, or null when it is shown.
     *
     * @param array<string, mixed> $values  The record's columns by name
     * @param bool                 $noindex Whether the page the site last rendered for it kept search engines out
     */
    public static function reason(SeoBlock $seo, array $values, bool $noindex = false): ?string
    {
        return match (true) {
            self::off($seo, $values) => self::BY_SWITCH,
            self::robots($seo, $values) => self::BY_ROBOTS,
            $noindex => self::BY_PAGE,
            default  => null,
        };
    }

    /**
     * @param array<string, mixed> $values
     */
    private static function robots(SeoBlock $seo, array $values): bool
    {
        return (
            null !== $seo->advanced
                && Noindex::in(Overrides::from($values[$seo->advanced] ?? null)['robots'] ?? null)
        );
    }
}

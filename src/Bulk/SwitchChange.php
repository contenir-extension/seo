<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Section\Hidden;
use Contenir\Seo\Sections\SeoBlock;

use function is_scalar;
use function trim;

/**
 * Turning a record's Discoverable switch on or off: the column Hidden names, and off as Hidden reads it.
 */
final readonly class SwitchChange
{
    /** What the switch holds when it is off and on. */
    public const string OFF = '0';

    public const string ON = '1';

    /**
     * The column and the value to write, or null when the section has no switch or the record is already so.
     *
     * @return array{string, ?string, string, ?string}|null The column, what it holds now, what to write and the key it is under
     */
    public static function of(FixKind $kind, SectionBlock $block, SeoBlock $seo, RecordRow $row): ?array
    {
        $switch = Hidden::column($seo);
        if (! $block->hasField($switch)) {
            return null;
        }

        $value = $row->value($switch);
        $now   = is_scalar($value) && '' !== trim((string) $value) ? (string) $value : null;
        $on    = ! Hidden::off($seo, $row->values);
        $after = FixKind::ShowInSearch === $kind ? self::ON : self::OFF;

        return $on === (self::ON === $after) ? null : [$switch, $now, $after, null];
    }
}

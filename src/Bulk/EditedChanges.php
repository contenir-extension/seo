<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function is_string;
use function mb_substr;
use function trim;

/**
 * What each change of a fix will write once the person has looked it over: the text edited into the review
 * table for a fix that allows it, with a change whose text was cleared left out.
 */
final class EditedChanges
{
    /** The longest text a person can write into a record from the review table. */
    public const int MAX_LENGTH = 500;

    /**
     * @param list<Change>            $changes
     * @param array<array-key, mixed> $after   The edited text, by "section|id"
     *
     * @return list<Change>
     */
    public static function of(array $changes, array $after): array
    {
        $kept = [];
        foreach ($changes as $change) {
            /** @var mixed $text */
            $text = $after[Selection::key($change->record)] ?? null;
            if (! $change->kind->editable() || ! is_string($text)) {
                $kept[] = $change;
                continue;
            }

            $written = mb_substr(trim($text), start: 0, length: self::MAX_LENGTH);
            if ('' !== $written) {
                $kept[] = $change->withAfter($written);
            }
        }

        return $kept;
    }
}

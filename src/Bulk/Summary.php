<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function array_shift;
use function html_entity_decode;
use function mb_strlen;
use function mb_strrpos;
use function mb_substr;
use function preg_match;
use function preg_replace;
use function rtrim;
use function strip_tags;
use function trim;

use const ENT_HTML5;
use const ENT_QUOTES;

/**
 * A description written from a record's own summary text: the markup taken out, the first sentences kept
 * while they fit what search shows, or else as many words as fit, ending where a word does.
 */
final class Summary
{
    /** How long a description search results show in full, in characters. */
    public const int LENGTH = 155;

    /** How short a summary can be before it is too little to describe a page, in characters. */
    public const int MINIMUM = 50;

    public static function of(string $text, int $max = self::LENGTH): ?string
    {
        $plain = self::plain($text);
        if ('' === $plain) {
            return null;
        }

        if (mb_strlen($plain) <= $max) {
            return $plain;
        }

        $head = mb_substr(
            string: $plain,
            start: 0,
            length: $max,
        );
        $sentence = self::sentence($head);
        if (null !== $sentence) {
            return $sentence;
        }

        $space = mb_strrpos($head, needle: ' ');

        $words = false === $space
            ? $head
            : mb_substr(
                string: $head,
                start: 0,
                length: $space,
            );

        return rtrim($words, characters: ' ,;:-') . '…';
    }

    /**
     * The text with its markup taken out and its runs of space made one, a break between blocks kept as a space.
     */
    private static function plain(string $text): string
    {
        $spaced  = (string) preg_replace('/<(br|\/p|\/li|\/h[1-6])\b[^>]*>/i', replacement: ' ', subject: $text);
        $decoded = html_entity_decode(strip_tags($spaced), flags: ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', replacement: ' ', subject: $decoded));
    }

    /**
     * The last full sentence in $head, when it is long enough to say something.
     */
    private static function sentence(string $head): ?string
    {
        $found = [];

        return 1 === preg_match('/.{40,}[.!?](?=\s|$)/u', $head, matches: $found) ? array_shift($found) : null;
    }
}

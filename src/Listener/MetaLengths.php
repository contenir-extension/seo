<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use function mb_strlen;
use function sprintf;

/**
 * What search results make of a title's and a description's length: a note
 * when they would cut it off, or a description is too short to say much.
 */
final class MetaLengths
{
    /** The most characters of a title search results show. */
    public const int TITLE_MAX = 60;

    /** The fewest characters of a description worth showing. */
    public const int DESCRIPTION_MIN = 70;

    /** The most characters of a description search results show, as the site summarises it. */
    public const int DESCRIPTION_MAX = 160;

    public static function description(string $description): ?string
    {
        $length = mb_strlen($description);

        return match (true) {
            $length < self::DESCRIPTION_MIN => sprintf(
                'The description is %d characters; search results have room for about %d.',
                $length,
                self::DESCRIPTION_MAX,
            ),
            $length > self::DESCRIPTION_MAX => sprintf(
                'The description is %d characters; search results cut it off after about %d.',
                $length,
                self::DESCRIPTION_MAX,
            ),
            default => null,
        };
    }

    /**
     * @param string $shown The title as search results show it, with the site name
     */
    public static function title(string $shown): ?string
    {
        $length = mb_strlen($shown);

        return $length > self::TITLE_MAX
            ? sprintf(
                'With the site name, the title is %d characters; search results cut it off after about %d.',
                $length,
                self::TITLE_MAX,
            )
            : null;
    }
}

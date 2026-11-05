<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Record\RecordRow;

use function hash;
use function json_encode;

/**
 * What the site rendered for a record when last asked, with what the record's
 * columns held then, so a change to them is noticed before the next check.
 */
final readonly class Inspection
{
    /**
     * @param string $stamp The record's columns when asked, as stamp() makes it
     * @param int $at When it was asked, as a Unix timestamp
     */
    public function __construct(
        public string $stamp,
        public int $at,
        public ?string $title,
        public ?string $description,
        public ?string $image,
    ) {}

    public static function of(RecordRow $row, SiteHead $head, int $at): self
    {
        return new self(self::stamp($row), $at, $head->title, $head->description, $head->image);
    }

    private static function stamp(RecordRow $row): string
    {
        return hash('sha256', (string) json_encode($row->values));
    }

    public function isCurrent(RecordRow $row): bool
    {
        return self::stamp($row) === $this->stamp;
    }
}

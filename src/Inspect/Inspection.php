<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Record\RecordRow;

use function hash;
use function is_int;
use function is_string;
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
     * @param int|null $imageWidth The width of the file its share image was made from, when known
     * @param int|null $imageHeight The height of that file, when known
     *
     * @mago-expect lint:excessive-parameter-list One value per thing kept of the site's answer.
     */
    public function __construct(
        public string $stamp,
        public int $at,
        public ?string $title,
        public ?string $description,
        public ?string $image,
        public ?int $imageWidth = null,
        public ?int $imageHeight = null,
    ) {}

    /**
     * An entry as the store keeps it, or null for one of another shape.
     *
     * @param array<array-key, mixed> $entry
     */
    public static function fromStored(array $entry): ?self
    {
        return is_string($entry['stamp'] ?? null) && is_int($entry['at'] ?? null)
            ? new self(
                $entry['stamp'],
                $entry['at'],
                self::text($entry['title'] ?? null),
                self::text($entry['description'] ?? null),
                self::text($entry['image'] ?? null),
                self::number($entry['imageWidth'] ?? null),
                self::number($entry['imageHeight'] ?? null),
            )
            : null;
    }

    /**
     * @param array{int, int}|null $imageSize The size of the file its share image was made from
     */
    public static function of(RecordRow $row, SiteHead $head, int $at, ?array $imageSize = null): self
    {
        return new self(
            self::stamp($row),
            $at,
            $head->title,
            $head->description,
            $head->image,
            $imageSize[0] ?? null,
            $imageSize[1] ?? null,
        );
    }

    private static function number(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private static function stamp(RecordRow $row): string
    {
        return hash('sha256', (string) json_encode($row->values));
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public function isCurrent(RecordRow $row): bool
    {
        return self::stamp($row) === $this->stamp;
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Url\RecordReference;

use function array_filter;
use function array_map;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function rename;
use function sprintf;
use function uniqid;

/**
 * The site's answers, kept in a file so the report never waits on the site. It
 * is only a cache: without its folder nothing is kept, and an entry of another
 * shape is dropped and asked for again.
 */
final readonly class InspectionStore
{
    public const string FILE = '/data/cache/contenir-seo-inspections.json';

    public function __construct(
        private string $file,
    ) {}

    public static function at(string $sitePath): self
    {
        return new self($sitePath . self::FILE);
    }

    public static function key(RecordReference $record): string
    {
        return sprintf('%s:%s', $record->section, $record->id);
    }

    /**
     * @return array<string, Inspection> By InspectionStore::key()
     */
    public function load(): array
    {
        /** @var mixed $values */
        $values = is_file($this->file) ? json_decode((string) file_get_contents($this->file), associative: true) : null;

        /** @var array<string, Inspection> */
        return array_filter(array_map(
            static fn(mixed $entry): ?Inspection => is_array($entry) ? Inspection::fromStored($entry) : null,
            is_array($values) ? $values : [],
        ));
    }

    /**
     * Writes through a temporary file, so a request never reads half of one.
     *
     * @param array<string, Inspection> $inspections
     */
    public function save(array $inspections): void
    {
        if (! is_dir(dirname($this->file))) {
            return;
        }

        $temporary = sprintf('%s.%s.tmp', $this->file, uniqid());
        if (false !== file_put_contents($temporary, (string) json_encode($inspections))) {
            rename($temporary, $this->file);
        }
    }
}

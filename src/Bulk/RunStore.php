<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function rename;
use function uniqid;
use function usort;

/**
 * The bulk fixes that have been run, newest first, kept in a file of the site's cache folder so an undo can
 * find what each one replaced. Only the most recent are kept; without the folder nothing is.
 */
final readonly class RunStore
{
    public const string FILE = '/data/cache/contenir-seo-runs.json';

    /** How many runs are kept. */
    public const int KEPT = 20;

    public function __construct(
        private string $file,
    ) {}

    public static function at(string $sitePath): self
    {
        return new self($sitePath . self::FILE);
    }

    /**
     * @return list<Run> Newest first
     */
    public function all(): array
    {
        /** @var mixed $values */
        $values = is_file($this->file) ? json_decode((string) file_get_contents($this->file), associative: true) : null;
        $runs   = array_values(array_filter(array_map(
            static fn(mixed $stored): ?Run => is_array($stored) ? RunCodec::decode($stored) : null,
            is_array($values) ? $values : [],
        )));
        usort($runs, static fn(Run $a, Run $b): int => $b->at <=> $a->at);

        return $runs;
    }

    public function find(string $id): ?Run
    {
        foreach ($this->all() as $run) {
            if ($id === $run->id) {
                return $run;
            }
        }

        return null;
    }

    /**
     * Keeps $run in place of the one with its id, or beside the others, and drops the oldest past KEPT.
     */
    public function put(Run $run): void
    {
        if (! is_dir(dirname($this->file))) {
            return;
        }

        $kept = array_filter($this->all(), static fn(Run $other): bool => $run->id !== $other->id);
        $runs = array_slice([$run, ...array_values($kept)], offset: 0, length: self::KEPT);
        usort($runs, static fn(Run $a, Run $b): int => $b->at <=> $a->at);
        $temporary = "{$this->file}." . uniqid() . '.tmp';
        if (false !== file_put_contents($temporary, (string) json_encode(array_map(RunCodec::encode(...), $runs)))) {
            rename($temporary, $this->file);
        }
    }
}

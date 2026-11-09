<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Section\Element;

use function array_key_exists;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function json_encode;
use function md5;
use function rename;
use function sprintf;
use function uniqid;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

/**
 * How a section is described for search where the site has changed it on this environment, kept in the site's
 * data folder as the `<seo>` block it replaces, so it survives a deploy and needs no change to admin.xml. A
 * section with none here is described as admin.xml says.
 */
final readonly class StrategyOverrides
{
    public const string FILE = '/data/seo-strategy.json';

    public function __construct(
        private string $file,
    ) {}

    public static function at(string $sitePath): self
    {
        return new self($sitePath . self::FILE);
    }

    public function customised(string $section): bool
    {
        return array_key_exists($section, $this->load());
    }

    /**
     * The section's saved `<seo>` block, or null where it is described as admin.xml says.
     */
    public function for(string $section): ?Element
    {
        $xml = $this->load()[$section] ?? null;
        if (null === $xml) {
            return null;
        }

        try {
            return Element::parse($xml);
        } catch (InvalidDeclarationException) {
            return null;
        }
    }

    /**
     * Returns the section to what admin.xml says.
     *
     * @throws StrategyNotSaved When the file changed since the form was opened, or can't be written.
     */
    public function reset(string $section, string $version): void
    {
        $all = $this->load();
        unset($all[$section]);
        $this->write($all, $version);
    }

    /**
     * @throws StrategyNotSaved When the file changed since the form was opened, or can't be written.
     */
    public function save(string $section, Element $block, string $version): void
    {
        $this->write([...$this->load(), $section => StrategyXml::write($block)], $version);
    }

    /**
     * An identifier of what is saved now, so a form opened before another save can be told apart.
     */
    public function version(): string
    {
        return is_file($this->file) ? md5((string) file_get_contents($this->file)) : '';
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        /** @var mixed $values */
        $values = is_file($this->file) ? json_decode((string) file_get_contents($this->file), associative: true) : null;
        $found  = [];
        /** @mago-expect analysis:mixed-assignment A stored file holds anything; only text is kept. */
        foreach (is_array($values) ? $values : [] as $section => $xml) {
            if (! is_string($section) || ! is_string($xml)) {
                continue;
            }

            $found[$section] = $xml;
        }

        return $found;
    }

    /**
     * @param array<string, string> $all
     *
     * @throws StrategyNotSaved
     */
    private function write(array $all, string $version): void
    {
        if ($version !== $this->version()) {
            throw StrategyNotSaved::changed();
        }

        if (! is_dir(dirname($this->file))) {
            throw StrategyNotSaved::notWritable();
        }

        if ([] === $all) {
            if (is_file($this->file)) {
                unlink($this->file);
            }

            return;
        }

        $temporary = sprintf('%s.%s.tmp', $this->file, uniqid());
        if (
            false === file_put_contents(
                $temporary,
                (string) json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            )
        ) {
            throw StrategyNotSaved::notWritable();
        }

        rename($temporary, $this->file);
    }
}

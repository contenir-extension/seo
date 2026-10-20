<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Trait;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

/**
 * A directory of its own for each test, removed afterwards with everything
 * in it.
 */
trait TempDirectoryTrait
{
    private string $directory = '';

    private function makeTempDirectory(): void
    {
        $this->directory = sys_get_temp_dir() . '/' . uniqid('contenir-extension-seo-', more_entropy: true);
        mkdir($this->directory);
    }

    private function removeTempDirectory(): void
    {
        if ('' === $this->directory || ! is_dir($this->directory)) {
            return;
        }

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->directory);
    }

    /**
     * Writes a config file, at $path inside the directory, returning $config.
     *
     * @param array<array-key, mixed> $config
     */
    private function writeConfig(string $path, array $config): void
    {
        $file = "{$this->directory}/{$path}";
        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), recursive: true);
        }

        file_put_contents($file, '<?php return ' . var_export($config, return: true) . ';');
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function preg_match;
use function trim;

/**
 * What a strategy form posted for one section, checked against the section: the schema type, the mode, each
 * role's sources in order, the alt text's source and which roles take what the Section field gives.
 *
 * @mago-expect lint:cyclomatic-complexity Each part of the form is read and checked on its own, a branch apiece.
 */
final readonly class StrategyForm
{
    private const string SCHEMA = '/^[A-Za-z][A-Za-z0-9]{0,63}$/';

    /**
     * @param array<array-key, mixed> $post
     */
    public function __construct(
        private SectionStrategy $current,
        private array $post,
    ) {}

    /**
     * @throws InvalidStrategy When it is not a field of the section or the one the block reads already.
     */
    public function alt(): string
    {
        if (! is_string($this->post['alt'] ?? null)) {
            return $this->current->altSource();
        }

        $name = trim($this->post['alt']);
        if ('' !== $name) {
            $this->known($name, [$this->current->altSource()]);
        }

        return $name;
    }

    /**
     * @throws InvalidStrategy When it is neither automatic nor manual.
     */
    public function automatic(): bool
    {
        $mode = $this->post['mode'] ?? ($this->current->automatic ? 'automatic' : 'manual');
        if (! in_array($mode, ['automatic', 'manual'], strict: true)) {
            throw new InvalidStrategy('The mode is automatic or manual.');
        }

        return 'automatic' === $mode;
    }

    /**
     * @throws InvalidStrategy When it is not a schema.org type name.
     */
    public function schema(): string
    {
        $schema = is_string($this->post['schema'] ?? null) ? trim($this->post['schema']) : $this->current->schema;
        if ('' !== $schema && 1 !== preg_match(self::SCHEMA, $schema)) {
            throw new InvalidStrategy("\"{$schema}\" is not a schema.org type name.");
        }

        return $schema;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidStrategy When a source is not a field of the section or one the block reads already.
     */
    public function sources(string $role): array
    {
        $sources = $this->post['sources'] ?? null;
        if (! is_array($sources) || ! is_array($sources[$role] ?? null)) {
            return $this->current->sourcesOf($role);
        }

        $posted = $sources[$role];
        $given  = [];
        /** @var mixed $source */
        foreach ($posted as $source) {
            $name = is_string($source) ? trim($source) : '';
            if ('' === $name) {
                continue;
            }

            $this->known($name, $this->current->sourcesOf($role));
            $given[] = $name;
        }

        return array_values(array_unique($given));
    }

    public function takesContent(string $role): bool
    {
        $content = $this->post['content'] ?? null;
        $posted  = is_array($content) ? $content[$role] ?? null : null;

        return match ($posted) {
            '1'     => true,
            '0'     => false,
            default => $this->current->takesContent($role),
        };
    }

    /**
     * @param list<string> $already
     *
     * @throws InvalidStrategy When $name is not a field of the section or one of $already.
     */
    private function known(string $name, array $already): void
    {
        if (! in_array($name, $this->current->fields, strict: true) && ! in_array($name, $already, strict: true)) {
            throw new InvalidStrategy("\"{$name}\" is not a field of {$this->current->title}.");
        }
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

/**
 * Something search results or shared links will make of a record: the role
 * it concerns, the field it belongs to (none for the whole record), what to
 * tell the editor, and whether the role has no value at all.
 */
final readonly class MetaIssue
{
    public function __construct(
        public string $role,
        public string $field,
        public string $message,
        public bool $missing = false,
    ) {}
}

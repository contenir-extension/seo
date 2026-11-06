<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

/**
 * What the Records screen's Fix menu can do to the records ticked.
 */
enum FixKind: string
{
    case WriteDescriptions = 'write-descriptions';
    case HideFromSearch    = 'hide';
    case ShowInSearch      = 'show';

    /**
     * Whether the person may edit what it will write before it is applied.
     */
    public function editable(): bool
    {
        return self::WriteDescriptions === $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::WriteDescriptions => 'Write descriptions from summary text',
            self::HideFromSearch => 'Hide from search',
            self::ShowInSearch => 'Show in search',
        };
    }

    /**
     * What the change is to, as the review table names it.
     */
    public function property(): string
    {
        return match ($this) {
            self::WriteDescriptions => 'Description',
            self::HideFromSearch, self::ShowInSearch => 'Visible in search',
        };
    }
}

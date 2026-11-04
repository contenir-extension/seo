<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Extension\Api\Event\Record\AbstractRecordPreEvent;
use Contenir\Seo\Sections\SeoBlock;

use function in_array;

/**
 * The save check's notes on a record: a missing value holds the save when
 * the section's block marks its role required, and is a warning otherwise;
 * anything else is a warning.
 */
final readonly class Notes
{
    public function __construct(
        private AbstractRecordPreEvent $event,
        private SeoBlock $seo,
    ) {}

    public function missing(string $role, string $field, string $message): void
    {
        in_array($role, $this->seo->required, strict: true)
            ? $this->event->addError($field, $message)
            : $this->event->warn($field, $message);
    }

    public function warn(string $field, ?string $message): void
    {
        if (null !== $message) {
            $this->event->warn($field, $message);
        }
    }
}

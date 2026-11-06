<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use function array_filter;
use function count;

/**
 * A bulk fix as it was asked for and how it went: who asked, when, what it was to change and what became
 * of each change. It keeps the values it replaced for a day, so it can be undone while they are still fresh.
 */
final readonly class Run
{
    /** How long after it was applied a run can be undone, in seconds. */
    public const int UNDO_WINDOW = 86_400;

    public const string QUEUED = 'queued';

    public const string RUNNING = 'running';

    public const string FINISHED = 'finished';

    /**
     * @param list<RunChange> $changes
     *
     * @mago-expect lint:excessive-parameter-list A run keeps who asked, when, what for, its changes and where it stands.
     */
    public function __construct(
        public string $id,
        public int $at,
        public string $editor,
        public FixKind $kind,
        public array $changes,
        public string $state = self::QUEUED,
        public ?int $undoneAt = null,
    ) {}

    /**
     * How many of the changes are in $status.
     */
    public function count(string $status): int
    {
        return count(array_filter($this->changes, static fn(RunChange $change): bool => $status === $change->status));
    }

    /**
     * Whether it has finished and still holds values worth putting back: within a day of it finishing, and not
     * undone already.
     */
    public function undoable(int $now): bool
    {
        return (
            self::FINISHED === $this->state
                && null === $this->undoneAt
                && $this->count(RunChange::DONE) > 0
                && ($now - $this->at) <= self::UNDO_WINDOW
        );
    }

    /**
     * @param list<RunChange> $changes
     */
    public function with(array $changes, string $state, ?int $undoneAt = null): self
    {
        return new self(
            $this->id,
            $this->at,
            $this->editor,
            $this->kind,
            $changes,
            $state,
            $undoneAt ?? $this->undoneAt,
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Url\RecordReference;

/**
 * One change in a run, and what became of it: waiting, saved, held back by the host, failed, left alone
 * because the record had changed since, or put back by an undo.
 */
final readonly class RunChange
{
    public const string PENDING = 'pending';

    public const string DONE = 'done';

    public const string STOPPED = 'stopped';

    public const string FAILED = 'failed';

    public const string SKIPPED = 'skipped';

    public const string UNDONE = 'undone';

    /**
     * @mago-expect lint:excessive-parameter-list A change keeps its record, how it is shown, what it holds before and after, and what became of it.
     */
    public function __construct(
        public RecordReference $record,
        public string $title,
        public string $sectionTitle,
        public string $column,
        public ?string $before,
        public string $after,
        public string $status = self::PENDING,
        public string $message = '',
    ) {}

    public static function of(Change $change): self
    {
        return new self(
            $change->record,
            $change->title,
            $change->sectionTitle,
            $change->column,
            $change->before,
            $change->after,
        );
    }

    public function with(string $status, string $message = ''): self
    {
        return new self(
            $this->record,
            $this->title,
            $this->sectionTitle,
            $this->column,
            $this->before,
            $this->after,
            $status,
            $message,
        );
    }

    public function withBefore(?string $before): self
    {
        return new self(
            $this->record,
            $this->title,
            $this->sectionTitle,
            $this->column,
            $before,
            $this->after,
            $this->status,
            $this->message,
        );
    }
}

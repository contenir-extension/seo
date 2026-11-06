<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Url\RecordReference;

/**
 * One value a fix would change on one record: the column, what it holds now and what it would hold.
 */
final readonly class Change
{
    /**
     * @mago-expect lint:excessive-parameter-list A change is one record's one column, with what it is called and what it holds before and after.
     */
    public function __construct(
        public RecordReference $record,
        public string $title,
        public string $sectionTitle,
        public FixKind $kind,
        public string $column,
        public ?string $before,
        public string $after,
        public ?string $key = null,
    ) {}

    /**
     * @return array{section: string, id: string|int, column: string, before: ?string, after: string}
     */
    public function toArray(): array
    {
        return [
            'section' => $this->record->section,
            'id'      => $this->record->id,
            'column'  => $this->column,
            'before'  => $this->before,
            'after'   => $this->after,
        ];
    }

    public function withAfter(string $after): self
    {
        return new self(
            $this->record,
            $this->title,
            $this->sectionTitle,
            $this->kind,
            $this->column,
            $this->before,
            $after,
            $this->key,
        );
    }
}

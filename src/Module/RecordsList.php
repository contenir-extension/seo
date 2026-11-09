<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Module\TemplateHelpersInterface;
use Contenir\Extension\Api\RecordList\RecordList;
use Contenir\Extension\Api\RecordList\RecordListAction;
use Contenir\Extension\Api\RecordList\RecordListBadge;
use Contenir\Extension\Api\RecordList\RecordListCell;
use Contenir\Extension\Api\RecordList\RecordListLink;
use Contenir\Extension\Api\RecordList\RecordListPager;
use Contenir\Extension\Api\RecordList\RecordListRow;
use Contenir\Extension\Api\RecordList\RecordListTone;
use Contenir\Extension\Seo\Bulk\FixKind;
use Contenir\Extension\Seo\Bulk\Selection;
use Contenir\Extension\Seo\Health\Tier;

use function array_map;

/**
 * The Records screen's list as the host draws it: a row per record with what it needs, leading to its
 * edit screen, its preview and its page, and the bulk fixes for the rows ticked.
 */
final readonly class RecordsList
{
    /**
     * @param list<RecordLine> $rows The records on this page
     */
    public function __construct(
        private RecordFilter $filter,
        private array $rows,
        private int $page,
        private int $total,
        private bool $bulk,
    ) {}

    private static function tone(Tier $tier): RecordListTone
    {
        return match ($tier) {
            Tier::High   => RecordListTone::Danger,
            Tier::Medium => RecordListTone::Warning,
            Tier::Low    => RecordListTone::Info,
        };
    }

    public function of(TemplateHelpersInterface $view): RecordList
    {
        $query = $this->filter->query();

        return new RecordList(
            ['Record', 'Section', 'To fix'],
            array_map(fn(RecordLine $line): RecordListRow => $this->row($line, $view), $this->rows),
            new RecordListPager(
                $this->page,
                RecordsHandler::PER_PAGE,
                $this->total,
                static fn(int $page): string => $view->url(RecordsHandler::NAME, $query + ['page' => $page]),
            ),
            emptyTitle: 'Nothing to fix',
            emptyDescription: 'No record matches these filters.',
            bulk: $this->bulk
                ? array_map(
                    static fn(FixKind $fix): RecordListAction => new RecordListAction(
                        $fix->label(),
                        $fix->icon(),
                        'fix',
                        $fix->value,
                    ),
                    FixKind::cases(),
                ) : [],
            bulkUrl: $this->bulk ? $view->url(FixHandler::NAME) : '',
            bulkValues: $query,
        );
    }

    private function row(RecordLine $line, TemplateHelpersInterface $view): RecordListRow
    {
        $record  = $line->row->record;
        $preview = new RecordListLink(
            'Preview in search',
            $view->url('preview', [
                'section' => $line->row->section->section,
                'id'      => $record->record->id,
            ]),
            'eye',
        );

        $edit = null === $line->edit ? [] : [new RecordListLink('Edit', $line->edit, 'pencil')];

        return new RecordListRow(
            Selection::key($record->record),
            $record->title,
            [
                RecordListCell::of($record->title, $line->edit),
                RecordListCell::of($line->row->section->title, muted: true),
                new RecordListCell(
                    $line->issues,
                    badges: array_map(
                        static fn(Tier $tier): RecordListBadge => new RecordListBadge(
                            $tier->label(),
                            self::tone($tier),
                        ),
                        $line->tiers,
                    ),
                ),
            ],
            null === $line->view
                ? [...$edit, $preview]
                : [...$edit, $preview, new RecordListLink('View the page', $line->view, 'external-link', newTab: true)],
        );
    }
}

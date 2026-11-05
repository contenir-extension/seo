<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Inspect;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Seo\Report\SectionRows;
use Psr\Container\ContainerExceptionInterface;

use function array_merge;

/**
 * Asks the site about the records whose answer is missing, out of date or a day
 * old, for up to a number of seconds at a time, since it renders a page for each
 * and a site that's down takes its whole timeout to say so.
 */
final readonly class RecordInspections
{
    /** How long an answer stands, since a page can change without its record's columns. */
    public const int MAX_AGE = 86_400;

    public function __construct(
        private ExtensionContextInterface $context,
    ) {}

    /**
     * @throws ContainerExceptionInterface When the host's service can't be reached.
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    public function refresh(int $seconds): Refreshed
    {
        $store   = InspectionStore::at($this->context->sitePath());
        $kept    = $store->load();
        $started = $this->now();
        $checked = 0;
        $missed  = 0;
        foreach ($this->rows() as $row) {
            $key = InspectionStore::key($row->record);
            if (! $this->needsAsking($row, $kept[$key] ?? null)) {
                continue;
            }

            if (($this->now() - $started) >= $seconds) {
                $store->save($kept);

                return new Refreshed($checked, $missed, finished: false);
            }

            $head = (new SiteInspector($this->context))->head($row->record);
            if (null === $head) {
                ++$missed;
                continue;
            }

            $kept[$key] = Inspection::of(
                $row,
                $head,
                $this->now(),
                (new ShareImageFile($this->context->sitePath()))->size($head->image),
            );
            ++$checked;
        }

        $store->save($kept);

        return new Refreshed($checked, $missed, finished: true);
    }

    private function needsAsking(RecordRow $row, ?Inspection $kept): bool
    {
        if (null === $this->context->urls()->url($row->record)) {
            return false;
        }

        return null === $kept || ! $kept->isCurrent($row) || ($this->now() - $kept->at) >= self::MAX_AGE;
    }

    private function now(): int
    {
        return $this->context->clock()->now()->getTimestamp();
    }

    /**
     * Every published record of the sections with pages of their own.
     *
     * @return list<RecordRow>
     *
     * @throws RowsRefusedException
     */
    private function rows(): array
    {
        $rows  = new SectionRows($this->context->sections());
        $found = [];
        foreach ($this->context->sections()->blocks() as $block) {
            $found[] = $block->linkable ? $rows->of($block) : [];
        }

        return array_merge([], ...$found);
    }
}

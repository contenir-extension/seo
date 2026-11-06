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
 * Asks the site about the records whose answer is missing, out of date (their
 * columns or the rendering state changed) or a day old, or on request any older than
 * the given age, for up to a number of seconds at a time, since it renders a page for each
 * and a site that's down takes its whole timeout to say so.
 */
final readonly class RecordInspections
{
    /** How long an answer stands, since a page can change without its record's columns. */
    public const int MAX_AGE = 86_400;

    /** How recent an answer is left alone when the editor asks for a fresh check, so a long site finishes over a few presses. */
    public const int RECENT = 300;

    public function __construct(
        private ExtensionContextInterface $context,
    ) {}

    /**
     * @throws ContainerExceptionInterface When the host's service can't be reached.
     * @throws RowsRefusedException When a section's columns can't be read.
     */
    public function refresh(int $seconds, int $maxAge = self::MAX_AGE): Refreshed
    {
        $store   = InspectionStore::at($this->context->sitePath());
        $kept    = $store->load();
        $started = $this->now();
        $state   = RenderingState::of($this->context);
        $checked = 0;
        $missed  = 0;
        foreach ($this->rows() as $row) {
            $key = InspectionStore::key($row->record);
            if (! $this->needsAsking($row, $kept[$key] ?? null, $state, $maxAge)) {
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
                $state,
                (new ShareImageFile($this->context->sitePath()))->size($head->image),
            );
            ++$checked;
        }

        $store->save($kept);

        return new Refreshed($checked, $missed, finished: true);
    }

    private function needsAsking(RecordRow $row, ?Inspection $kept, string $state, int $maxAge): bool
    {
        if (null === $this->context->urls()->url($row->record)) {
            return false;
        }

        if (null === $kept || ! $kept->isCurrent($row, $state)) {
            return true;
        }

        return ($this->now() - $kept->at) >= $maxAge;
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

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Inspect\InspectionStore;
use Contenir\Extension\Seo\Inspect\RenderingState;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\SeoSettings;
use Psr\Container\ContainerExceptionInterface;

use function array_filter;
use function array_map;
use function array_values;

/**
 * Every record of the sections the add-on describes for search, going by what
 * the site last rendered for it where it has been asked, and otherwise checked
 * as a save checks one, from its columns, where what a site builds in its own
 * code isn't seen. It also finds what only shows across a section: titles two
 * records shown to search share, and published records hidden from it, which
 * are noted as hidden and asked for nothing else.
 */
final readonly class SearchReport
{
    /** The roles a record's issues fall under, with what the report calls each. */
    public const array ROLES = [
        'title'        => 'Title',
        'description'  => 'Description',
        'image'        => 'Image',
        'discoverable' => 'Hidden',
    ];

    public const int PAGE = 100;

    /** The most records read from a section, so a report never waits on a very long one. */
    public const int MAX_ROWS = 5000;

    public function __construct(
        private ExtensionContextInterface $context,
    ) {}

    /**
     * The sections with pages of their own, in the order admin.xml lists them.
     *
     * @return list<SectionIssues>
     *
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting the title depends on isn't one of the add-on's.
     */
    public function sections(): array
    {
        $titles = $this->titles();
        $config = (new SeoSettings())->config($this->context);
        $scan   = new SectionScan(
            $this->context->sections(),
            $config->title,
            InspectionStore::at($this->context->sitePath())->load(),
            RenderingState::of($this->context),
            $config->robots->value,
        );
        $blocks = array_filter(
            SeoBlocks::of($this->context)->all(),
            static fn(SectionBlock $block): bool => $block->linkable,
        );

        return array_values(array_map(
            static fn(SectionBlock $block): SectionIssues => $scan->of(
                $block,
                $titles[$block->section] ?? $block->section,
            ),
            $blocks,
        ));
    }

    /**
     * What the report calls each linkable section, by its key: the section's own heading, or else the site's name for a record as a plural.
     *
     * @return array<string, string>
     *
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     */
    private function titles(): array
    {
        return SectionHeadings::of($this->context);
    }
}

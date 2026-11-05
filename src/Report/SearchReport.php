<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Report;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\SeoSettings;
use Psr\Container\ContainerExceptionInterface;

use function array_filter;
use function array_map;
use function array_values;

/**
 * Every record of the sections the add-on describes for search, checked as a
 * save checks one, from its raw columns: what a site builds in its own code,
 * such as a description from a page's blocks, isn't seen. It also finds what
 * only shows across a section: titles two records share, and published records
 * hidden from search.
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

    public const string HIDDEN = 'Hidden from search: its Discoverable switch is off.';

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
        $scan   = new SectionScan($this->context->sections(), (new SeoSettings())->config($this->context)->title);
        $blocks = array_filter(
            $this->context->sections()->blocks(),
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
     * What the site calls each linkable section, by its key.
     *
     * @return array<string, string>
     *
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     */
    private function titles(): array
    {
        $titles = [];
        foreach ($this->context->services()->get(RecordsInterface::class)->sections() as $section) {
            $titles[$section->section] = $section->title;
        }

        return $titles;
    }
}

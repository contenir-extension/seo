<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Panel;

use Contenir\Extension\Seo\Inspect\Inspection;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaIssues;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Report\InspectionIssues;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\Title\TitleTemplate;

/**
 * What the panel says of a record: its title as search shows it, its description
 * and what it still needs, from what the site rendered or from its own columns.
 *
 * @internal
 */
final readonly class PanelFacts
{
    /**
     * @param list<MetaIssue> $issues
     * @param ?array{int, int} $imageSize The share image's width and height, when the site said
     * @param list<string> $derived The roles the record's Section field gives, which its columns don't
     *
     * @mago-expect lint:excessive-parameter-list One for each thing the panel says of a record; most are optional.
     */
    private function __construct(
        public ?string $title,
        public ?string $description,
        public array $issues,
        public ?string $image = null,
        public ?array $imageSize = null,
        public array $derived = [],
    ) {}

    public static function ofColumns(SeoBlock $seo, MetaValues $values, TitleTemplate $template): self
    {
        $title   = $values->text('title');
        $derived = $values->derivedDescription();

        return new self(
            null === $title ? null : $template->format($title),
            $values->shownDescription(),
            (new MetaIssues($seo, $template))->of($values),
            derived: [
                ...(null === $derived ? [] : ['description']),
                ...($values->hasDerivedImage() ? ['image'] : []),
            ],
        );
    }

    public static function ofSite(Inspection $site): self
    {
        return new self(
            $site->title,
            $site->description,
            InspectionIssues::of($site),
            $site->image,
            null === $site->imageWidth || null === $site->imageHeight ? null : [$site->imageWidth, $site->imageHeight],
        );
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Seo\Report\SectionIssues;

/**
 * What each section is called, for the review and run screens.
 */
final class SectionTitles
{
    /**
     * @param list<SectionIssues> $sections
     *
     * @return array<string, string> Each section's title, by section
     */
    public static function of(array $sections): array
    {
        $titles = [];
        foreach ($sections as $section) {
            $titles[$section->section] = $section->title;
        }

        return $titles;
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Seo\Report\RecordIssues;
use Contenir\Extension\Seo\Report\SectionIssues;

/**
 * A record in the report, with the section it is in.
 */
final readonly class ReportRow
{
    public function __construct(
        public SectionIssues $section,
        public RecordIssues $record,
    ) {}
}

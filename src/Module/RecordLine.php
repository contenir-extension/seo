<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

/**
 * What the Records screen shows of a record.
 */
final readonly class RecordLine
{
    /**
     * @param list<string> $issues
     * @param string|null $edit Where it is edited in the CMS
     * @param string|null $view Its page on the site
     */
    public function __construct(
        public ReportRow $row,
        public array $issues,
        public ?string $edit,
        public ?string $view,
    ) {}
}

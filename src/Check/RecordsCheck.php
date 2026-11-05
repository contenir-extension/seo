<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Check;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\CheckInterface;
use Contenir\Extension\Api\Diagnostic\CheckResult;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Seo\Report\SearchReport;
use Contenir\Extension\Seo\Report\SectionIssues;
use Override;
use Psr\Container\ContainerExceptionInterface;

use function array_filter;
use function array_map;
use function count;
use function implode;
use function sprintf;

/**
 * How many records of the sections described for search still need something.
 */
final readonly class RecordsCheck implements CheckInterface
{
    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function check(ExtensionContextInterface $context): CheckResult
    {
        $sections = (new SearchReport($context))->sections();
        if ([] === $sections) {
            return CheckResult::skipped('No section with pages of its own declares an <seo> block in admin.xml.');
        }

        $flagged = array_filter($sections, static fn(SectionIssues $section): bool => [] !== $section->records);

        return [] === $flagged
            ? CheckResult::success(sprintf(
                'Every record in %d %s is ready for search.',
                count($sections),
                1 === count($sections) ? 'section' : 'sections',
            ))
            : CheckResult::warning(sprintf(
                'Records need attention for search: %s. See SEO in the menu.',
                implode('; ', array_map(
                    static fn(SectionIssues $section): string => sprintf(
                        '%s: %d',
                        $section->title,
                        count($section->records),
                    ),
                    $flagged,
                )),
            ));
    }

    #[Override]
    public function label(): string
    {
        return 'Records ready for search';
    }
}

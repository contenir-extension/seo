<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Record\RecordsInterface;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Report\SearchReport;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function array_slice;
use function ceil;
use function count;
use function filter_var;
use function max;
use function min;

use const FILTER_VALIDATE_INT;

/**
 * The search report: the records that need something, filtered by section and
 * role, a page at a time, each leading to its edit screen and its page on the site.
 */
final readonly class RecordsHandler implements HandlerInterface
{
    public const string NAME = 'records';

    public const int PER_PAGE = 50;

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When a tab or the template is malformed.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $query    = $request->getQueryParams();
        $sections = (new SearchReport($context))->sections();
        $filter   = RecordFilter::from($query, $sections);
        $rows     = $filter->rows($sections);
        $pages    = (int) ceil(count($rows) / self::PER_PAGE);
        $page     = max(1, min($pages, (int) filter_var($query['page'] ?? null, FILTER_VALIDATE_INT)));
        $records  = $context->services()->get(RecordsInterface::class);

        return HandlerResult::template(
            'records',
            'SEO records',
            [
                'sections' => $sections,
                'roles'    => SearchReport::ROLES,
                'filter'   => $filter,
                'total'    => count($rows),
                'page'     => $page,
                'pages'    => $pages,
                'from'     => (($page - 1) * self::PER_PAGE) + 1,
                'rows'     => array_map(
                    static fn(ReportRow $row): RecordLine => new RecordLine(
                        $row,
                        array_map(
                            static fn(MetaIssue $issue): string => $issue->message,
                            $filter->issuesOf($row->record),
                        ),
                        $records->describe($row->record->record)?->editUrl,
                        $context->urls()->url($row->record->record),
                    ),
                    array_slice($rows, ($page - 1) * self::PER_PAGE, length: self::PER_PAGE),
                ),
            ],
            Tabs::of(self::NAME),
        );
    }
}

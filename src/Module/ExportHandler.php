<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Seo\Report\SearchReport;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_map;
use function fputcsv;
use function in_array;
use function substr;

/**
 * The search report as a CSV file, a line per issue, filtered as the screen is.
 */
final readonly class ExportHandler implements HandlerInterface
{
    public const string NAME = 'records-export';

    private const array FORMULA = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * A cell a spreadsheet won't run as a formula, since a record's title is
     * whatever an editor typed.
     */
    private static function cell(string $text): string
    {
        return in_array(substr($text, offset: 0, length: 1), self::FORMULA, strict: true) ? "'{$text}" : $text;
    }

    /**
     * @param resource $stream
     * @param list<string> $cells
     *
     * @mago-expect analysis:missing-parameter-type A stream has no native type.
     */
    private static function line($stream, array $cells): void
    {
        fputcsv($stream, array_map(self::cell(...), $cells), escape: '');
    }

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When the file name or media type is malformed.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $sections = (new SearchReport($context))->sections();
        $filter   = RecordFilter::from($request->getQueryParams(), $sections);
        $rows     = $filter->rows($sections);

        return HandlerResult::download(
            'seo-records.csv',
            'text/csv',
            static function ($stream) use ($rows, $filter): void {
                self::line($stream, ['Section', 'Record', 'Area', 'Issue']);
                foreach ($rows as $row) {
                    foreach ($row->record->in($filter->role) as $issue) {
                        self::line($stream, [
                            $row->section->title,
                            $row->record->title,
                            SearchReport::ROLES[$issue->role] ?? $issue->role,
                            $issue->message,
                        ]);
                    }
                }
            },
        );
    }
}

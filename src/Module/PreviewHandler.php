<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Handler\HandlerInterface;
use Contenir\Extension\Api\Handler\HandlerResult;
use Contenir\Extension\Api\Handler\ParentPage;
use Contenir\Extension\Api\Record\RecordRow;
use Contenir\Extension\Api\Section\SectionBlock;
use Contenir\Extension\Seo\Inspect\SiteInspector;
use Contenir\Extension\Seo\Listener\MetaValues;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\Section\SeoSectionBlock;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;

/**
 * One record as search and sharing sites would show it, as the site renders it
 * now: its search result, its share card and its structured data. When the site
 * can't be asked, it shows what the record's own columns hold, and says so.
 */
final readonly class PreviewHandler implements HandlerInterface
{
    public const string NAME = 'preview';

    /**
     * @throws RowsRefusedException
     */
    private static function row(ExtensionContextInterface $context, SectionBlock $block, ?string $id): ?RecordRow
    {
        $columns = SeoSectionBlock::known($block, SeoDeclaration::read($block->element)->columns());

        return null === $id ? null : $context->sections()->rows($block->section, $columns, [$id])[0] ?? null;
    }

    /**
     * @param array<array-key, mixed> $query
     */
    private static function text(array $query, string $key): ?string
    {
        return is_string($query[$key] ?? null) ? $query[$key] : null;
    }

    /**
     * @throws ContainerExceptionInterface When the host's service can't be reached.
     * @throws InvalidDeclarationException When the template or its parent is malformed.
     * @throws RowsRefusedException When the section's columns can't be read.
     */
    #[Override]
    public function handle(ServerRequestInterface $request, ExtensionContextInterface $context): HandlerResult
    {
        $query   = $request->getQueryParams();
        $section = self::text($query, 'section');
        $block   = null === $section ? null : $context->sections()->block($section);
        $row     = null === $block ? null : self::row($context, $block, self::text($query, 'id'));
        if (null === $block || null === $row) {
            return HandlerResult::back()->withMessage('warning', 'That record can\'t be previewed.');
        }

        return HandlerResult::template(
            'preview',
            'Search preview',
            PreviewValues::of(
                (new SiteInspector($context))->head($row->record),
                SeoDeclaration::read($block->element),
                new MetaValues($block, $row->values),
                $context->urls()->url($row->record),
            ),
            [],
            new ParentPage('Records', RecordsHandler::NAME),
        );
    }
}

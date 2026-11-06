<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Panel;

use Contenir\Extension\Api\Panel\Link;
use Contenir\Extension\Api\Panel\PanelRow;
use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\MetaLengths;

use function array_map;
use function mb_strlen;
use function sprintf;
use function usort;

/**
 * What the record panel says needs fixing, one short row for each, the most important first: what it is
 * ("Description"), a few words on how ("Missing", "Short, 40 characters"), and a Fix link. Only what is
 * wrong is here; what is right is left out.
 *
 * @internal
 */
final class PanelRows
{
    /**
     * @param list<MetaIssue> $issues
     *
     * @return list<PanelRow>
     */
    public static function of(PanelFacts $facts, array $issues, ?Link $fix): array
    {
        $kinds = array_map(IssueKind::of(...), $issues);
        usort(
            $kinds,
            static fn(IssueKind $a, IssueKind $b): int => $b->tier()->weight() <=> $a->tier()->weight(),
        );

        return array_map(
            static fn(IssueKind $kind): PanelRow => new PanelRow(self::label($kind), self::detail($kind, $facts), $fix),
            $kinds,
        );
    }

    private static function descriptionLength(int $length): string
    {
        return $length < MetaLengths::DESCRIPTION_MIN
            ? sprintf('Short (%d characters)', $length)
            : sprintf('Too long (%d of about %d)', $length, MetaLengths::DESCRIPTION_MAX);
    }

    private static function detail(IssueKind $kind, PanelFacts $facts): string
    {
        return match ($kind) {
            IssueKind::TitleLength => sprintf(
                'Too long (%d of about %d)',
                mb_strlen((string) $facts->title),
                MetaLengths::TITLE_MAX,
            ),
            IssueKind::DescriptionLength => self::descriptionLength(mb_strlen((string) $facts->description)),
            IssueKind::SmallImage        => self::smallImage($facts),
            IssueKind::DuplicateTitle    => 'Another record has it',
            IssueKind::Hidden            => 'Hidden',
            default                      => 'Missing',
        };
    }

    private static function label(IssueKind $kind): string
    {
        return match ($kind->role()) {
            'title'       => 'Title',
            'description' => 'Description',
            'image'       => 'Share image',
            default       => 'Search',
        };
    }

    private static function smallImage(PanelFacts $facts): string
    {
        return null === $facts->imageSize
            ? 'Small'
            : sprintf('Small (%d×%d)', $facts->imageSize[0], $facts->imageSize[1]);
    }
}

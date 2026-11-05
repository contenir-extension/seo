<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

use Contenir\Seo\Sections\LinkedValue;
use Contenir\Seo\Sections\SeoBlock;
use Contenir\Seo\Title\TitleTemplate;

use function array_filter;
use function array_values;

/**
 * What search results and shared links will make of a record's own values:
 * a missing title, description or image, a title they would cut off once
 * the site's template adds the site name, and a description outside the
 * length they show. Whether another record has the title is the save
 * check's to ask, as it reads the section.
 */
final readonly class MetaIssues
{
    public function __construct(
        private SeoBlock $seo,
        private TitleTemplate $template,
    ) {}

    private static function issue(string $role, string $field, ?string $message): ?MetaIssue
    {
        return null === $message ? null : new MetaIssue($role, $field, $message);
    }

    /**
     * The issue of a role with no value in its columns, which isn't one while
     * it can still come from a linked record, as only the site can tell.
     *
     * @param list<string> $columns
     */
    private static function missing(string $role, string $field, array $columns, string $message): ?MetaIssue
    {
        return LinkedValue::any($columns) ? null : new MetaIssue($role, $field, $message, missing: true);
    }

    /**
     * @return list<MetaIssue>
     */
    public function of(MetaValues $values): array
    {
        $issues   = [];
        $columns  = $this->seo->roles['title'] ?? [];
        $title    = $values->text($columns);
        $issues[] = null === $title
            ? self::missing('title', $values->field($columns), $columns, 'Add a title for search results.')
            : self::issue('title', $values->field($columns), MetaLengths::title($this->template->format($title)));

        $columns     = $this->seo->roles['description'] ?? [];
        $description = $values->text($columns);
        $issues[]    = null === $description
            ? self::missing(
                'description',
                $values->field($columns),
                $columns,
                'Add a description for search results and shared links.',
            )
            : self::issue('description', $values->field($columns), MetaLengths::description($description));

        $columns  = $this->seo->roles['image'] ?? [];
        $issues[] = [] !== $columns && null === $values->text($columns)
            ? self::missing('image', $values->field($columns), $columns, 'Add an image for shared links.')
            : null;

        return array_values(array_filter($issues));
    }
}

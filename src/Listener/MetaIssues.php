<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Listener;

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
     * @return list<MetaIssue>
     */
    public function of(MetaValues $values): array
    {
        $issues   = [];
        $columns  = $this->seo->roles['title'] ?? [];
        $title    = $values->text($columns);
        $issues[] = null === $title
            ? new MetaIssue('title', $values->field($columns), 'Add a title for search results.', missing: true)
            : self::issue('title', $values->field($columns), MetaLengths::title($this->template->format($title)));

        $columns     = $this->seo->roles['description'] ?? [];
        $description = $values->text($columns);
        $issues[]    = null === $description
            ? new MetaIssue(
                'description',
                $values->field($columns),
                'Add a description for search results and shared links.',
                missing: true,
            )
            : self::issue('description', $values->field($columns), MetaLengths::description($description));

        $columns  = $this->seo->roles['image'] ?? [];
        $issues[] = [] !== $columns && null === $values->text($columns)
            ? new MetaIssue('image', $values->field($columns), 'Add an image for shared links.', missing: true)
            : null;

        return array_values(array_filter($issues));
    }
}

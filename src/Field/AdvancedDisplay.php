<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Field\FieldDisplayLine;
use Contenir\Seo\Sections\Overrides;
use Contenir\Seo\Sections\SeoBlock;

use function array_key_exists;

/**
 * How a record's own SEO values read on its View page: each by name, then a description its Section field
 * gives where its own columns give none, noted as taken from the content.
 */
final readonly class AdvancedDisplay
{
    /** The note on a description the record doesn't hold but its Section field gives. */
    public const string FROM_CONTENT = 'From content';

    /** The note on the same description in a manual section, where the page doesn't use it. */
    public const string SUGGESTED = 'Suggested from content';

    /** What each override is called, in the order they show. */
    private const array LABELS = [
        'title'       => 'Title',
        'description' => 'Description',
        'image_alt'   => 'Share image description',
        'canonical'   => 'Canonical address',
        'robots'      => 'Robots',
        'schema'      => 'Schema type',
    ];

    /**
     * @param array<string, mixed> $record  The record's values by column name
     * @param ?SeoBlock            $section The record's section's block, when it is known: a manual section only suggests
     *                                      the description, and a role that takes no content has none to take
     *
     * @return list<FieldDisplayLine>
     */
    public function of(mixed $value, array $record, ?SeoBlock $section = null): array
    {
        $overrides = Overrides::from($value);
        $note      = null === $section || $section->automatic ? self::FROM_CONTENT : self::SUGGESTED;
        $derived   = array_key_exists('description', $overrides) ? null : DescriptionFromContent::of($record, $section);
        $lines     = [];
        foreach (self::LABELS as $name => $label) {
            $text = 'description' === $name && null !== $derived ? $derived : $overrides[$name] ?? null;
            if (null === $text) {
                continue;
            }

            $lines[] = new FieldDisplayLine($label, $text, $text === $derived ? $note : null);
        }

        return $lines;
    }
}

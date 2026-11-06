<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Field\FieldDisplayLine;
use Contenir\Extension\Seo\Section\DerivedContent;
use Contenir\Seo\Sections\Overrides;
use Contenir\Seo\Sections\SeoBlock;

use function array_key_exists;
use function is_string;
use function trim;

/**
 * How a record's own SEO values read on its View page: each by name, then a description its Section field
 * gives where its own columns give none, noted as taken from the content.
 */
final readonly class AdvancedDisplay
{
    /** The note on a description the record doesn't hold but its Section field gives. */
    public const string FROM_CONTENT = 'From content';

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
     * Whether the record's own columns hold a description, as a section reads them by convention.
     *
     * @param array<string, mixed> $record
     */
    private static function holdsDescription(array $record): bool
    {
        foreach (SeoBlock::CONVENTIONS['description'] as $column) {
            /** @var mixed $text */
            $text = $record[$column] ?? null;
            if (is_string($text) && '' !== trim($text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $record The record's values by column name
     *
     * @return list<FieldDisplayLine>
     */
    public function of(mixed $value, array $record): array
    {
        $overrides = Overrides::from($value);
        $derived   = array_key_exists('description', $overrides) || self::holdsDescription($record)
            ? null
            : (new DerivedContent($record))->description();
        $lines = [];
        foreach (self::LABELS as $name => $label) {
            $text = 'description' === $name && null !== $derived ? $derived : $overrides[$name] ?? null;
            if (null === $text) {
                continue;
            }

            $lines[] = new FieldDisplayLine($label, $text, $text === $derived ? self::FROM_CONTENT : null);
        }

        return $lines;
    }
}

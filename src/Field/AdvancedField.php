<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\SectionDisplayedFieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Seo\Sections\Overrides;
use Override;

use function array_diff_key;
use function array_is_list;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function trim;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * A record's own SEO values, kept as JSON in one column: its meta title and
 * description, share image's description, canonical address, robots rule and
 * schema type. Its image comes from its section's image role, so an override of
 * it in the JSON isn't kept.
 * The host draws a textarea, which the AdvancedField component turns into a
 * group of inputs; without the script an editor can still type the JSON.
 */
final class AdvancedField implements SectionDisplayedFieldTypeInterface
{
    public const string NAME = 'advanced';

    public const string COMPONENT = '[data-seo-advanced]';

    public const string SCRIPT = 'js/AdvancedField.js';

    /** The override a section's image role already gives a record. */
    private const array OWN_COLUMNS = ['image' => true];

    private static function isObject(string $json): bool
    {
        /** @var mixed $decoded */
        $decoded = json_decode($json, associative: true);

        return is_array($decoded) && ([] === $decoded || ! array_is_list($decoded));
    }

    #[Override]
    public function component(): ?string
    {
        return self::COMPONENT;
    }

    #[Override]
    public function control(): FieldType
    {
        return FieldType::Textarea;
    }

    /**
     * @param array<string, mixed> $record
     */
    #[Override]
    public function display(mixed $value, array $record): array
    {
        return (new AdvancedDisplay())->of($value, $record);
    }

    /**
     * @param array<string, mixed> $record
     */
    #[Override]
    public function displayIn(string $section, mixed $value, array $record, ExtensionContextInterface $context): array
    {
        $block = $context->sections()->block($section);

        return (new AdvancedDisplay())->of(
            $value,
            $record,
            null === $block ? null : SeoDeclaration::read($block->element),
        );
    }

    /**
     * The overrides that hold something, as JSON, or null with none, so an
     * empty field stores nothing in a JSON column. A value that isn't an object
     * stays as it is, since the host also runs this over what it draws: the
     * editor then sees it, and validate() refuses it, rather than it being lost.
     */
    #[Override]
    public function normalise(mixed $value): mixed
    {
        if (is_string($value) && '' !== trim($value) && ! self::isObject($value)) {
            return $value;
        }

        $overrides = array_diff_key(Overrides::from($value), self::OWN_COLUMNS);

        return [] === $overrides
            ? null
            : json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        return [];
    }

    #[Override]
    public function validate(mixed $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        if (! is_string($value) || ! self::isObject($value)) {
            return ['The SEO overrides could not be read.'];
        }

        return AdvancedRules::problems(Overrides::from($value));
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\ControlDataInterface;
use Contenir\Extension\Api\Field\SectionDisplayedFieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Extension\Seo\Section\SeoBlocks;
use Contenir\Extension\Seo\Section\SeoDeclaration;
use Contenir\Extension\Seo\SeoSettings;
use Contenir\Seo\Sections\Overrides;
use Override;

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
 * description, share image and its description, canonical address, robots
 * rule and schema type. The share image is a path or address, or `@site` for
 * the site's share image in place of the page's own. The control is given
 * what the page uses for each when it isn't overridden (ControlDefaults).
 * The host draws a textarea, which the AdvancedField component turns into a
 * group of inputs; without the script an editor can still type the JSON.
 */
final class AdvancedField implements SectionDisplayedFieldTypeInterface, ControlDataInterface
{
    public const string NAME = 'advanced';

    public const string COMPONENT = '[data-seo-advanced]';

    public const string SCRIPT = 'js/AdvancedField.js';

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
     * What the page uses for each of the tab's fields when the record doesn't override it, or nothing for a
     * section without an `<seo>` block.
     *
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function controlData(string $section, mixed $value, array $record, ExtensionContextInterface $context): array
    {
        $block = '' === $section ? null : SeoBlocks::of($context)->one($section);

        return (
            null === $block
                ? []
                : ControlDefaults::of(
                    $block,
                    SeoDeclaration::read($block->element),
                    $record,
                    (new SeoSettings())->config($context),
                )
        );
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
        $block = SeoBlocks::of($context)->one($section);

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

        $overrides = Overrides::from($value);

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

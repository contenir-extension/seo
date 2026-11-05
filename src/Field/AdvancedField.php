<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Seo\Sections\Overrides;
use Override;

use function array_is_list;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * A record's own SEO overrides, kept as JSON in one column: share title,
 * description, image and alt, canonical address, robots rule and schema type.
 * The host draws a textarea, which the AdvancedField component turns into a
 * group of inputs; without the script an editor can still type the JSON.
 */
final class AdvancedField implements FieldTypeInterface
{
    public const string NAME = 'advanced';

    public const string COMPONENT = '[data-seo-advanced]';

    public const string SCRIPT = 'js/AdvancedField.js';

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
     * The overrides that hold something, as JSON, or null with none, so an
     * empty field stores nothing in a JSON column.
     */
    #[Override]
    public function normalise(mixed $value): mixed
    {
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

        /** @var mixed $decoded */
        $decoded = is_string($value) ? json_decode($value, associative: true) : null;
        if (! is_array($decoded) || ([] !== $decoded && array_is_list($decoded))) {
            return ['The SEO overrides could not be read.'];
        }

        return AdvancedRules::problems(Overrides::from($value));
    }
}

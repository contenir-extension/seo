<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Seo\Robots\RobotsRules;
use Override;

use function is_string;

/**
 * Extra robots.txt rules, one per line: seo:robots-rules. Each line is
 * checked as it is saved, and stored with its directive spelt as robots.txt
 * spells it.
 *
 * @api
 */
final class RobotsRulesField implements FieldTypeInterface
{
    public const string NAME = 'robots-rules';

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    #[Override]
    public function component(): ?string
    {
        return null;
    }

    #[Override]
    public function control(): FieldType
    {
        return FieldType::Textarea;
    }

    #[Override]
    public function normalise(mixed $value): mixed
    {
        return RobotsRules::parse(self::text($value))->text();
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        return [];
    }

    #[Override]
    public function validate(mixed $value): array
    {
        return RobotsRules::problems(self::text($value));
    }
}

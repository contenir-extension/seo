<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Field\FieldTypeInterface;
use Contenir\Extension\Api\Form\FieldType;
use Contenir\Seo\Verification\Token;
use Contenir\Seo\Verification\Verifier;
use Override;

use function is_string;

/**
 * A text field holding one service's verification token, checked as it is
 * saved. A pasted meta tag is stored as its token.
 *
 * @api
 */
abstract class AbstractTokenField implements FieldTypeInterface
{
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
        return FieldType::Text;
    }

    #[Override]
    public function normalise(mixed $value): mixed
    {
        return Token::normalise(self::text($value));
    }

    #[Override]
    public function options(ExtensionContextInterface $context): array
    {
        return [];
    }

    #[Override]
    public function validate(mixed $value): array
    {
        $problem = Token::problem($this->verifier(), self::text($value));

        return null === $problem ? [] : [$problem];
    }

    /**
     * The service whose token the field holds.
     */
    abstract protected function verifier(): Verifier;
}

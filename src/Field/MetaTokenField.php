<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Seo\Verification\Verifier;
use Override;

/**
 * A field holding the Meta (Facebook and Instagram) verification token: seo:meta-verification.
 *
 * @api
 */
final class MetaTokenField extends AbstractTokenField
{
    public const string NAME = 'meta-verification';

    #[Override]
    protected function verifier(): Verifier
    {
        return Verifier::Meta;
    }
}

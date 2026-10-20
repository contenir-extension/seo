<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Seo\Verification\Verifier;
use Override;

/**
 * A field holding the Pinterest verification token: seo:pinterest-verification.
 *
 * @api
 */
final class PinterestTokenField extends AbstractTokenField
{
    public const string NAME = 'pinterest-verification';

    #[Override]
    protected function verifier(): Verifier
    {
        return Verifier::Pinterest;
    }
}

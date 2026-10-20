<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Seo\Verification\Verifier;
use Override;

/**
 * A field holding the Google Search Console verification token: seo:google-verification.
 *
 * @api
 */
final class GoogleTokenField extends AbstractTokenField
{
    public const string NAME = 'google-verification';

    #[Override]
    protected function verifier(): Verifier
    {
        return Verifier::Google;
    }
}

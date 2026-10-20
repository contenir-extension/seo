<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Field;

use Contenir\Seo\Verification\Verifier;
use Override;

/**
 * A field holding the Bing Webmaster Tools verification token: seo:bing-verification.
 *
 * @api
 */
final class BingTokenField extends AbstractTokenField
{
    public const string NAME = 'bing-verification';

    #[Override]
    protected function verifier(): Verifier
    {
        return Verifier::Bing;
    }
}

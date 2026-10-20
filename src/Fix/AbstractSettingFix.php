<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Fix;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Diagnostic\FixInterface;
use Contenir\Extension\Api\Diagnostic\FixResult;
use Override;

/**
 * A fix that saves one of the section's switches, as the settings screen
 * would.
 *
 * @api
 */
abstract class AbstractSettingFix implements FixInterface
{
    #[Override]
    public function apply(array $values, ExtensionContextInterface $context): FixResult
    {
        $result = $context->settings()->set($this->values());

        return $result->succeeded()
            ? FixResult::applied($this->done())
            : FixResult::failed('' === $result->message ? 'The setting could not be saved.' : $result->message);
    }

    /**
     * What to tell the person who applied it.
     */
    abstract protected function done(): string;

    /**
     * @return array<string, mixed> By field name
     */
    abstract protected function values(): array;
}

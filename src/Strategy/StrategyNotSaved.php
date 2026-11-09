<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use RuntimeException;

use function trim;

/**
 * A section's strategy that couldn't be saved here, and why.
 */
final class StrategyNotSaved extends RuntimeException
{
    public static function changed(): self
    {
        return new self('This was changed by someone else since you opened it. Open it again to see what changed.');
    }

    public static function notWritable(string $reason = ''): self
    {
        return new self(trim("The site's settings could not be saved, so the change was not saved. {$reason}"));
    }
}

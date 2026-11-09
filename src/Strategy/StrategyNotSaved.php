<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use RuntimeException;

/**
 * A section's strategy that couldn't be saved here, and why.
 */
final class StrategyNotSaved extends RuntimeException
{
    public static function changed(): self
    {
        return new self('This was changed by someone else since you opened it. Open it again to see what changed.');
    }

    public static function notWritable(): self
    {
        return new self('The site\'s data folder can\'t be written to, so the change was not saved.');
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Bulk;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;

use function array_merge;
use function array_values;
use function implode;

/**
 * What became of a save, as a run keeps it: its status, and what it said when it did not go through.
 */
final class Outcomes
{
    /**
     * Why it was held, from its field errors or else its message; nothing when it went through.
     */
    public static function reason(ActionResult $result): string
    {
        if ($result->succeeded()) {
            return '';
        }

        $errors = array_values(array_merge([], ...array_values($result->errors)));

        return [] === $errors ? $result->message : implode(' ', $errors);
    }

    public static function status(ActionResult $result): string
    {
        return match ($result->outcome) {
            Outcome::Done    => RunChange::DONE,
            Outcome::Stopped => RunChange::STOPPED,
            Outcome::Failed  => RunChange::FAILED,
        };
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Bulk;

use Contenir\Extension\Api\Event\ActionResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Seo\Bulk\Outcomes;
use Contenir\Extension\Seo\Bulk\RunChange;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('bulk')]
final class OutcomesTest extends TestCase
{
    #[Test]
    public function givesNoReasonForAChangeThatWentThrough(): void
    {
        static::assertSame('', Outcomes::reason(new ActionResult(Outcome::Done, 1, 'Saved.')));
    }

    #[Test]
    public function givesTheFieldErrorsAsTheReasonItWasHeld(): void
    {
        $result = new ActionResult(Outcome::Stopped, 1, 'Not saved.', errors: [
            'a' => ['One.', 'Two.'],
            'b' => ['Three.'],
        ]);

        static::assertSame('One. Two. Three.', Outcomes::reason($result));
    }

    #[Test]
    public function givesTheMessageWhenThereAreNoFieldErrors(): void
    {
        static::assertSame('Not saved.', Outcomes::reason(new ActionResult(Outcome::Failed, 1, 'Not saved.')));
    }

    #[Test]
    public function namesTheStatusOfEachOutcome(): void
    {
        static::assertSame(
            [RunChange::DONE, RunChange::STOPPED, RunChange::FAILED],
            [
                Outcomes::status(new ActionResult(Outcome::Done, 1)),
                Outcomes::status(new ActionResult(Outcome::Stopped, 1)),
                Outcomes::status(new ActionResult(Outcome::Failed, 1)),
            ],
        );
    }
}

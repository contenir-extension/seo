<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Section;

use Contenir\Extension\Seo\Section\StrategySection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class StrategySectionTest extends TestCase
{
    #[Test]
    public function isKeptOutOfTheSitesMenusAndHoldsItsOneFieldForThisEnvironmentOnly(): void
    {
        $section = StrategySection::define();
        $field   = $section->form->fields[0];

        static::assertSame(
            [true, StrategySection::NAME, StrategySection::SECTIONS, true],
            [$section->hidden, $section->name, $field->name, $field->local],
        );
    }
}

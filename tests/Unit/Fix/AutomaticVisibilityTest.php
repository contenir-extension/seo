<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Fix;

use Contenir\Extension\Api\Diagnostic\FixResult;
use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Seo\Fix\AutomaticVisibility;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\SettingsContext;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class AutomaticVisibilityTest extends TestCase
{
    #[Test]
    public function explainsAFailedSaveWithoutAMessage(): void
    {
        $settings = new ArraySettings(AppearanceSection::define(), outcome: Outcome::Stopped);

        $result = (new AutomaticVisibility())->apply([], new SettingsContext(appearance: $settings));

        static::assertEquals(FixResult::failed('The setting could not be saved.'), $result);
    }

    #[Test]
    public function passesOnTheSettingsMessageWhenTheSaveFails(): void
    {
        $settings = new ArraySettings(AppearanceSection::define(), outcome: Outcome::Stopped, message: 'Saving is off');

        $result = (new AutomaticVisibility())->apply([], new SettingsContext(appearance: $settings));

        static::assertEquals(FixResult::failed('Saving is off'), $result);
    }

    #[Test]
    public function setsSearchEnginesBackToAutomatic(): void
    {
        $settings = new ArraySettings(AppearanceSection::define(), ['visibility' => 'hide']);

        $result = (new AutomaticVisibility())->apply([], new SettingsContext(appearance: $settings));

        static::assertEquals(
            [FixResult::applied('Search engines are set to Automatic on this environment.'), ['visibility' => 'auto']],
            [$result, $settings->saved],
        );
    }
}

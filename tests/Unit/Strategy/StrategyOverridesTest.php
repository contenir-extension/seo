<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Strategy;

use Contenir\Extension\Api\Event\Outcome;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Seo\Section\StrategySection;
use Contenir\Extension\Seo\Strategy\StrategyNotSaved;
use Contenir\Extension\Seo\Strategy\StrategyOverrides;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\ArraySettings;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * What a site saves about how a section is described, kept in the local settings file.
 */
#[Group('unit')]
final class StrategyOverridesTest extends TestCase
{
    private ?ArraySettings $settings = null;

    #[Test]
    public function changesItsVersionWithWhatIsSaved(): void
    {
        $overrides = $this->overrides();
        $overrides->save('library.a.b', Element::parse('<seo type="one"/>'), '');
        $first = $overrides->version();
        $overrides->save('library.a.b', Element::parse('<seo type="two"/>'), $first);

        static::assertNotSame($first, $overrides->version());
    }

    #[Test]
    public function ignoresStoredTextThatIsNotWhatItWrote(): void
    {
        $overrides = $this->overrides(['sections' => '{"library.a.b": 5, "x": "<seo", "ok": "<seo type=\"t\"/>"}']);

        static::assertSame([null, null, 't'], [
            $overrides->for('library.a.b'),
            $overrides->for('x'),
            $overrides->for('ok')?->attribute('type'),
        ]);
    }

    #[Test]
    public function keepsEverySectionSavedSoFar(): void
    {
        $overrides = $this->overrides();
        $overrides->save('library.a.b', Element::parse('<seo type="one"/>'), '');
        $overrides->save('library.c.d', Element::parse('<seo type="two"/>'), $overrides->version());

        static::assertSame(['one', 'two'], [
            $overrides->for('library.a.b')?->attribute('type'),
            $overrides->for('library.c.d')?->attribute('type'),
        ]);
    }

    #[Test]
    public function knowsNothingOfASectionNeverSaved(): void
    {
        static::assertNull($this->overrides()->for('library.a.b'));
    }

    #[Test]
    public function readsBackWhatWasSaved(): void
    {
        $overrides = $this->overrides();
        $overrides->save(
            'library.a.b',
            Element::parse('<seo type="x" schema="Article"><title>meta_title, title</title></seo>'),
            '',
        );

        $block = $overrides->for('library.a.b');

        static::assertSame(['Article', 'meta_title, title'], [
            $block?->attribute('schema'),
            $block?->child('title')?->text,
        ]);
    }

    #[Test]
    public function refusesASaveMadeFromAVersionThatMovedOn(): void
    {
        $overrides = $this->overrides();
        $overrides->save('library.a.b', Element::parse('<seo type="one"/>'), '');

        $this->expectExceptionObject(StrategyNotSaved::changed());

        $overrides->save('library.a.b', Element::parse('<seo type="two"/>'), 'stale');
    }

    #[Test]
    public function refusesASaveTheSettingsDidNotAccept(): void
    {
        $this->expectExceptionObject(StrategyNotSaved::notWritable('Held by a listener.'));

        $this->overrides([], Outcome::Failed, 'Held by a listener.')->save(
            'library.a.b',
            Element::parse('<seo/>'),
            '',
        );
    }

    #[Test]
    public function returnsASectionToTheDefaults(): void
    {
        $overrides = $this->overrides();
        $overrides->save('library.a.b', Element::parse('<seo/>'), '');
        $overrides->reset('library.a.b', $overrides->version());

        static::assertNull($overrides->for('library.a.b'));
    }

    #[Test]
    public function saysWhichSectionsWereSaved(): void
    {
        $overrides = $this->overrides();
        $overrides->save('library.a.b', Element::parse('<seo/>'), '');

        static::assertSame([true, false], [
            $overrides->customised('library.a.b'),
            $overrides->customised('library.c.d'),
        ]);
    }

    #[Test]
    public function storesNothingWhenNothingIsLeftToKeep(): void
    {
        $overrides = $this->overrides();
        $overrides->save('library.a.b', Element::parse('<seo/>'), '');
        $overrides->reset('library.a.b', $overrides->version());

        static::assertSame('', $overrides->version());
    }

    #[Test]
    public function treatsAValueThatIsNotTextAsNothingSaved(): void
    {
        static::assertSame('', $this->overrides(['sections' => 5])->version());
    }

    /**
     * @param array<string, mixed> $values
     */
    private function overrides(
        array $values = [],
        Outcome $outcome = Outcome::Done,
        string $message = '',
    ): StrategyOverrides {
        $this->settings ??= new ArraySettings(StrategySection::define(), $values, $outcome, $message);

        return new StrategyOverrides($this->settings);
    }
}

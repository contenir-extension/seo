<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Check;

use Contenir\Extension\Api\Diagnostic\GuidedFix;
use Contenir\Extension\Api\Diagnostic\OneClickFix;
use Contenir\Extension\Api\Diagnostic\Status;
use Contenir\Extension\Seo\Check\VisibilityCheck;
use Contenir\Extension\Seo\Environment;
use Contenir\Extension\Seo\Fix\AutomaticVisibility;
use Contenir\Extension\Seo\Tests\TestAsset\Settings\SettingsContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class VisibilityCheckTest extends TestCase
{
    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function fine(): array
    {
        return [
            'production, automatic' => ['production', 'auto', 'Production is shown to search engines.'],
            'production, shown'     => ['production', 'show', 'Production is shown to search engines.'],
            'production, not text'  => ['production', 7, 'Production is shown to search engines.'],
            'staging, automatic'    => ['staging', 'auto', 'This staging copy is hidden from search engines.'],
            'development, hidden'   => ['development', 'hide', 'This development copy is hidden from search engines.'],
        ];
    }

    private static function context(mixed $visibility): SettingsContext
    {
        return new SettingsContext(['seo' => ['visibility' => $visibility]]);
    }

    #[Test]
    public function hasALabel(): void
    {
        static::assertSame('Search engines and this environment', (new VisibilityCheck())->label());
    }

    #[DataProvider('fine')]
    #[Test]
    public function passesWhenProductionIsShownAndCopiesHidden(
        string $environment,
        mixed $visibility,
        string $message,
    ): void {
        $result = (new VisibilityCheck(new Environment($environment)))->check(self::context($visibility));

        static::assertSame([Status::Success, $message, null], [$result->status, $result->message, $result->fix]);
    }

    #[Test]
    public function warnsWhenACopyIsShown(): void
    {
        $result = (new VisibilityCheck(new Environment('staging')))->check(self::context('show'));
        $fix    = $result->fix;

        static::assertSame(
            [
                Status::Warning,
                'This staging copy is shown to search engines, so it could appear in search results beside the live'
                    . ' site.',
                'Hide this copy',
                'Setting search engines to Automatic hides this staging copy, as long as its site URL is the live'
                    . ' site\'s. It changes this environment only: production stays in search results.',
                AutomaticVisibility::class,
            ],
            [
                $result->status,
                $result->message,
                $fix instanceof GuidedFix ? $fix->label : null,
                $fix instanceof GuidedFix ? $fix->explanation : null,
                $fix instanceof GuidedFix ? $fix->handler : null,
            ],
        );
    }

    #[Test]
    public function warnsWhenProductionIsHidden(): void
    {
        $result = (new VisibilityCheck(new Environment('production')))->check(self::context('hide'));
        $fix    = $result->fix;

        static::assertSame(
            [
                Status::Warning,
                'Production is hidden from search engines, so the live site will drop out of search results.',
                'Set to Automatic',
                AutomaticVisibility::class,
            ],
            [
                $result->status,
                $result->message,
                $fix instanceof OneClickFix ? $fix->label : null,
                $fix instanceof OneClickFix ? $fix->handler : null,
            ],
        );
    }
}

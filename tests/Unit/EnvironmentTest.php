<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit;

use Contenir\Extension\Seo\Environment;
use Override;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function define;
use function getenv;
use function putenv;

#[Group('unit')]
final class EnvironmentTest extends TestCase
{
    private string|false $before = false;

    #[Test]
    public function isNotProductionByAnyOtherName(): void
    {
        static::assertFalse((new Environment('staging'))->isProduction());
    }

    #[Test]
    public function isProductionByName(): void
    {
        static::assertTrue((new Environment('production'))->isProduction());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function readsTheCmssConstantFirst(): void
    {
        putenv('APPLICATION_ENV=staging');
        define('APPLICATION_ENV', value: 'development');

        static::assertSame('development', (new Environment())->name);
    }

    #[Test]
    public function readsTheEnvironmentVariable(): void
    {
        putenv('APPLICATION_ENV=staging');

        static::assertSame('staging', (new Environment())->name);
    }

    #[Test]
    public function takesAMissingEnvironmentAsProduction(): void
    {
        putenv('APPLICATION_ENV');

        static::assertSame('production', (new Environment())->name);
    }

    #[Test]
    public function takesAnUnnamedEnvironmentAsProduction(): void
    {
        putenv('APPLICATION_ENV=');

        static::assertSame('production', (new Environment())->name);
    }

    #[Override]
    protected function setUp(): void
    {
        $this->before = getenv('APPLICATION_ENV');
    }

    #[Override]
    protected function tearDown(): void
    {
        putenv(false === $this->before ? 'APPLICATION_ENV' : "APPLICATION_ENV={$this->before}");
    }
}

<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Listener;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Seo\Listener\SiteTitle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class SiteTitleTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function settingsProvider(): array
    {
        return [
            'as set'                   => [
                ['site_name' => 'Studio Esteta', 'title_separator' => '–', 'title_position' => 'before'],
                'Studio Esteta – House',
            ],
            'an unknown position'      => [
                ['site_name' => 'Studio Esteta', 'title_position' => 'middle'],
                'House | Studio Esteta',
            ],
            'values that are not text' => [['site_name' => 3, 'title_separator' => 4, 'title_position' => 5], 'House'],
        ];
    }

    /**
     * @param array<string, mixed> $values
     */
    #[Test]
    #[DataProvider('settingsProvider')]
    public function formatsATitleAsSearchAppearanceSets(array $values, string $title): void
    {
        $settings = $this->createStub(SettingsInterface::class);
        $settings->method('get')
            ->willReturnCallback(
                static fn(string $field, mixed $default = null): mixed => $values[$field] ?? $default,
            );
        $context = $this->createStub(ExtensionContextInterface::class);
        $context->method('settings')->willReturn($settings);

        static::assertSame($title, SiteTitle::template($context)->format('House'));
    }
}

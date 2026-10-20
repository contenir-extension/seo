<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\TestAsset\Settings;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Handle;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Api\Url\UrlResolverInterface;
use Contenir\Extension\Seo\Section\AppearanceSection;
use Contenir\Extension\Seo\Section\SitemapSection;
use Contenir\Extension\Seo\Section\VerificationSection;
use LogicException;
use Override;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * A context that offers the add-on's three sections, each with the values
 * given, and a site path, and nothing else.
 */
final readonly class SettingsContext implements ExtensionContextInterface
{
    /**
     * @var array<string, SettingsInterface>
     */
    public array $sections;

    /**
     * @param array<string, array<string, mixed>> $values By section, then field
     */
    public function __construct(
        array $values = [],
        private string $sitePath = '/site',
        ?SettingsInterface $appearance = null,
    ) {
        $handle         = new Handle('seo');
        $this->sections = [
            AppearanceSection::NAME   => $appearance
            ?? new ArraySettings(AppearanceSection::define(), $values[AppearanceSection::NAME] ?? []),
            VerificationSection::NAME => new ArraySettings(
                VerificationSection::define($handle),
                $values[VerificationSection::NAME] ?? [],
            ),
            SitemapSection::NAME      => new ArraySettings(
                SitemapSection::define($handle),
                $values[SitemapSection::NAME] ?? [],
            ),
        ];
    }

    #[Override]
    public function clock(): ClockInterface
    {
        throw new LogicException('Not offered');
    }

    #[Override]
    public function events(): EventDispatcherInterface
    {
        throw new LogicException('Not offered');
    }

    #[Override]
    public function handle(): Handle
    {
        return new Handle('seo');
    }

    #[Override]
    public function logger(): LoggerInterface
    {
        throw new LogicException('Not offered');
    }

    #[Override]
    public function services(): ContainerInterface
    {
        throw new LogicException('Not offered');
    }

    #[Override]
    public function settings(?string $section = null): SettingsInterface
    {
        return (
            $this->sections[$section ?? AppearanceSection::NAME] ?? throw UnknownSettingException::section(
                'seo',
                (string) $section,
            )
        );
    }

    #[Override]
    public function sitePath(): string
    {
        return $this->sitePath;
    }

    #[Override]
    public function urls(): UrlResolverInterface
    {
        throw new LogicException('Not offered');
    }
}

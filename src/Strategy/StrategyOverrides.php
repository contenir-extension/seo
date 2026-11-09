<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Api\Settings\SettingsInterface;
use Contenir\Extension\Seo\Section\StrategySection;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function md5;

use const JSON_UNESCAPED_SLASHES;

/**
 * How a section is described for search where the site has changed it on this environment, kept in the site's
 * local settings file as the `<seo>` block it replaces, so it survives a deploy and needs no change to admin.xml.
 * A section with none here is described as admin.xml says.
 */
final readonly class StrategyOverrides
{
    public function __construct(
        private SettingsInterface $settings,
    ) {}

    public static function of(ExtensionContextInterface $context): self
    {
        return new self($context->settings(StrategySection::NAME));
    }

    public function customised(string $section): bool
    {
        return array_key_exists($section, $this->load());
    }

    /**
     * The section's saved `<seo>` block, or null where it is described as admin.xml says.
     */
    public function for(string $section): ?Element
    {
        $xml = $this->load()[$section] ?? null;
        if (null === $xml) {
            return null;
        }

        try {
            return Element::parse($xml);
        } catch (InvalidDeclarationException) {
            return null;
        }
    }

    /**
     * Returns the section to what admin.xml says.
     *
     * @throws StrategyNotSaved When the settings changed since the form was opened, or can't be saved.
     */
    public function reset(string $section, string $version): void
    {
        $all = $this->load();
        unset($all[$section]);
        $this->write($all, $version);
    }

    /**
     * @throws StrategyNotSaved When the settings changed since the form was opened, or can't be saved.
     */
    public function save(string $section, Element $block, string $version): void
    {
        $this->write([...$this->load(), $section => StrategyXml::write($block)], $version);
    }

    /**
     * An identifier of what is saved now, so a form opened before another save can be told apart.
     */
    public function version(): string
    {
        $stored = $this->stored();

        return '' === $stored ? '' : md5($stored);
    }

    /**
     * @return array<string, string>
     */
    private function load(): array
    {
        /** @var mixed $values */
        $values = json_decode($this->stored(), associative: true);
        $found  = [];
        /** @mago-expect analysis:mixed-assignment Stored text holds anything; only text is kept. */
        foreach (is_array($values) ? $values : [] as $section => $xml) {
            if (! is_string($section) || ! is_string($xml)) {
                continue;
            }

            $found[$section] = $xml;
        }

        return $found;
    }

    private function stored(): string
    {
        $value = $this->settings->get(StrategySection::SECTIONS, '');

        return is_string($value) ? $value : '';
    }

    /**
     * @param array<string, string> $all
     *
     * @throws StrategyNotSaved
     */
    private function write(array $all, string $version): void
    {
        if ($version !== $this->version()) {
            throw StrategyNotSaved::changed();
        }

        $result = $this->settings->set([
            StrategySection::SECTIONS => [] === $all ? '' : (string) json_encode($all, JSON_UNESCAPED_SLASHES),
        ]);
        if (! $result->succeeded()) {
            throw StrategyNotSaved::notWritable($result->message);
        }
    }
}

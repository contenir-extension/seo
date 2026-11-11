<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Strategy;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\BlockNotSavedException;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Section\Element;
use Contenir\Extension\Seo\Section\StrategySection;

use function is_array;
use function is_string;
use function json_decode;
use function json_encode;

use const JSON_UNESCAPED_SLASHES;

/**
 * The strategies a site saved while they were kept in its local settings, which the host could not see, so
 * it refused the columns they named. Each is handed to the host as an override of the section's block and
 * taken out of the settings, once: a section already overridden keeps what it has, one whose block is gone
 * or whose strategy no longer reads as a block is let go, and one the host would not keep stays in the
 * settings to be tried again.
 */
final readonly class SettingsStrategies
{
    public function __construct(
        private ExtensionContextInterface $context,
    ) {}

    /**
     * @return array<string, string> Each section's block as XML
     */
    private static function stored(mixed $value): array
    {
        /** @var mixed $values */
        $values = is_string($value) ? json_decode($value, associative: true) : null;
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

    public function move(): void
    {
        try {
            $settings = $this->context->settings(StrategySection::NAME);
        } catch (UnknownSettingException) {
            return;
        }

        $stored = self::stored($settings->get(StrategySection::SECTIONS, ''));
        if ([] === $stored) {
            return;
        }

        $left = [];
        foreach ($stored as $section => $xml) {
            if ($this->moved($section, $xml)) {
                continue;
            }

            $left[$section] = $xml;
        }

        $settings->set([
            StrategySection::SECTIONS => [] === $left ? '' : json_encode($left, JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Whether the settings are done with $section's strategy: the host keeps it now, or it has nothing to keep.
     */
    private function moved(string $section, string $xml): bool
    {
        $sections = $this->context->sections();
        if (null === $sections->declared($section) || $sections->overridden($section)) {
            return true;
        }

        try {
            $sections->override($section, Element::parse($xml), $sections->overridesVersion());

            return true;
        } catch (InvalidDeclarationException) {
            return true;
        } catch (BlockNotSavedException $refused) {
            $this->context->logger()->warning(
                'The strategy saved for {section} could not be handed to the site: {reason}',
                ['section' => $section, 'reason' => $refused->getMessage()],
            );

            return false;
        }
    }
}

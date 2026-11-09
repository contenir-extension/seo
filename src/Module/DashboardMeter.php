<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Module;

use Contenir\Extension\Api\Context\ExtensionContextInterface;
use Contenir\Extension\Api\Exception\InvalidDeclarationException;
use Contenir\Extension\Api\Exception\RowsRefusedException;
use Contenir\Extension\Api\Exception\UnknownSettingException;
use Contenir\Extension\Api\Module\Meter;
use Contenir\Extension\Api\Module\MeterInterface;
use Contenir\Extension\Api\Module\MeterSegment;
use Contenir\Extension\Api\Module\MeterTone;
use Contenir\Extension\Seo\Health\SiteChecks;
use Contenir\Extension\Seo\Health\SiteHealth;
use Contenir\Extension\Seo\Health\Tier;
use Contenir\Extension\Seo\Report\SearchReport;
use Override;
use Psr\Container\ContainerExceptionInterface;

/**
 * How the site reads in search, for the dashboard's SEO card: the score and what it means, when the site was
 * last asked, and the issues to fix as one bar divided by impact.
 */
final readonly class DashboardMeter implements MeterInterface
{
    /** What the dashboard's card is called, where the area's own name, SEO, would say less. */
    public const string HEADING = 'SEO ranking';

    /**
     * @throws ContainerExceptionInterface When the host's records can't be reached.
     * @throws InvalidDeclarationException When a part of the meter is malformed.
     * @throws RowsRefusedException When a section's columns can't be read.
     * @throws UnknownSettingException When a setting isn't one of the add-on's.
     */
    #[Override]
    public function meter(ExtensionContextInterface $context): ?Meter
    {
        $sections = (new SearchReport($context))->sections();
        $health   = SiteHealth::of($sections, SiteChecks::of($context));
        $score    = $health->score;
        if ([] === $sections || null === $score->value) {
            return null;
        }

        $ago = OverviewHandler::checkedAgo($context);

        return new Meter(
            (string) $score->value,
            $score->label(),
            match ($score->tone()) {
                'success' => MeterTone::Good,
                'warning' => MeterTone::Warning,
                default   => MeterTone::Poor,
            },
            null === $ago ? 'Not checked with the site yet' : "Checked {$ago} ago",
            $this->segments($health),
            self::HEADING,
        );
    }

    /**
     * @return list<MeterSegment>
     *
     * @throws InvalidDeclarationException When a label is empty.
     */
    private function segments(SiteHealth $health): array
    {
        $segments = [];
        foreach ([Tier::High, Tier::Medium, Tier::Low] as $tier) {
            $count = 0;
            foreach ($health->fixFirst() as $issue) {
                $count += $tier === $issue->kind->tier() ? $issue->count : 0;
            }

            if (0 === $count) {
                continue;
            }

            $segments[] = new MeterSegment($tier->label() . ' impact', $count, match ($tier) {
                Tier::High   => MeterTone::Poor,
                Tier::Medium => MeterTone::Warning,
                Tier::Low    => MeterTone::Neutral,
            });
        }

        return $segments;
    }
}

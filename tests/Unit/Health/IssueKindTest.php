<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Tests\Unit\Health;

use Contenir\Extension\Seo\Health\IssueKind;
use Contenir\Extension\Seo\Health\Tier;
use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\TitleTaken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class IssueKindTest extends TestCase
{
    /**
     * @return array<string, array{MetaIssue, IssueKind}>
     */
    public static function issueProvider(): array
    {
        $noTitle = new MetaIssue('title', 'title', 'Add.', missing: true);
        $noText  = new MetaIssue('description', 'summary', 'Add.', missing: true);
        $noImage = new MetaIssue('image', 'image', 'Add.', missing: true);
        $shared  = new MetaIssue('title', 'title', TitleTaken::MESSAGE);
        $short   = new MetaIssue('description', 'summary', 'Short.');

        return [
            'a missing title'       => [$noTitle, IssueKind::MissingTitle],
            'a shared title'        => [$shared, IssueKind::DuplicateTitle],
            'a long title'          => [new MetaIssue('title', 'title', 'Cut off.'), IssueKind::TitleLength],
            'a missing description' => [$noText, IssueKind::MissingDescription],
            'a short description'   => [$short, IssueKind::DescriptionLength],
            'a missing image'       => [$noImage, IssueKind::MissingImage],
            'a small image'         => [new MetaIssue('image', '', 'Soft.'), IssueKind::SmallImage],
            'a hidden record'       => [new MetaIssue('discoverable', 'switch', 'Hidden.'), IssueKind::Hidden],
        ];
    }

    #[Test]
    public function knowsWhichKindsAreAboutTheWholeSite(): void
    {
        $site = [];
        foreach (IssueKind::cases() as $kind) {
            if (! $kind->siteWide()) {
                continue;
            }

            $site[] = $kind->value;
        }

        static::assertSame(['sitemap-off', 'defaults-not-set'], $site);
    }

    #[Test]
    public function namesTheRoleARecordKindBelongsToAndNoneForSiteWideOnes(): void
    {
        static::assertSame(
            ['title', 'description', 'image', 'discoverable', null],
            [
                IssueKind::DuplicateTitle->role(),
                IssueKind::DescriptionLength->role(),
                IssueKind::SmallImage->role(),
                IssueKind::Hidden->role(),
                IssueKind::SitemapOff->role(),
            ],
        );
    }

    #[Test]
    public function ranksTheKindsInThreeTiers(): void
    {
        $tiers = [];
        foreach (IssueKind::cases() as $kind) {
            $tiers[$kind->tier()->value][] = $kind->value;
        }

        $high = ['missing-title', 'missing-description', 'duplicate-title', 'sitemap-off', 'defaults-not-set'];

        static::assertSame(
            [
                'high'   => $high,
                'medium' => ['title-length', 'missing-image'],
                'low'    => ['description-length', 'small-image', 'hidden'],
            ],
            $tiers,
        );
    }

    #[DataProvider('issueProvider')]
    #[Test]
    public function readsARecordsIssueAsOneKind(MetaIssue $issue, IssueKind $kind): void
    {
        static::assertSame($kind, IssueKind::of($issue));
    }

    #[Test]
    public function saysWhatAnEditorCallsEachKind(): void
    {
        static::assertSame(
            ['Missing description', 'Duplicate title', 'Search defaults not set', 'High'],
            [
                IssueKind::MissingDescription->label(),
                IssueKind::DuplicateTitle->label(),
                IssueKind::DefaultsNotSet->label(),
                Tier::High->label(),
            ],
        );
    }

    #[Test]
    public function weighsHighTenMediumFourAndLowOne(): void
    {
        static::assertSame([10, 4, 1], [Tier::High->weight(), Tier::Medium->weight(), Tier::Low->weight()]);
    }
}

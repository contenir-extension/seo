<?php

declare(strict_types=1);

namespace Contenir\Extension\Seo\Health;

use Contenir\Extension\Seo\Listener\MetaIssue;
use Contenir\Extension\Seo\Listener\TitleTaken;

use function in_array;

/**
 * The kinds of thing that can be wrong, each with how much it matters. A record's issue is read as one
 * kind from its role and its message; the site-wide kinds come from the add-on's own checks. Only what
 * can be checked is here, so a score never rests on a guess.
 */
enum IssueKind: string
{
    case MissingTitle       = 'missing-title';
    case MissingDescription = 'missing-description';
    case DuplicateTitle     = 'duplicate-title';
    case TitleLength        = 'title-length';
    case DescriptionLength  = 'description-length';
    case MissingImage       = 'missing-image';
    case SmallImage         = 'small-image';
    case Hidden             = 'hidden';
    case SitemapOff         = 'sitemap-off';
    case DefaultsNotSet     = 'defaults-not-set';

    /** The kinds a high-impact issue is. */
    private const array HIGH = [
        self::MissingTitle,
        self::MissingDescription,
        self::DuplicateTitle,
        self::SitemapOff,
        self::DefaultsNotSet,
    ];

    /** The kinds a medium-impact issue is. */
    private const array MEDIUM = [
        self::TitleLength,
        self::MissingImage,
    ];

    /**
     * Which kind a record's issue is.
     */
    public static function of(MetaIssue $issue): self
    {
        return match ($issue->role) {
            'title' => match (true) {
                $issue->missing => self::MissingTitle,
                TitleTaken::MESSAGE === $issue->message => self::DuplicateTitle,
                default => self::TitleLength,
            },
            'description' => $issue->missing ? self::MissingDescription : self::DescriptionLength,
            'image'       => $issue->missing ? self::MissingImage : self::SmallImage,
            default       => self::Hidden,
        };
    }

    /**
     * What an editor calls it.
     */
    public function label(): string
    {
        return match ($this) {
            self::MissingTitle => 'Missing title',
            self::MissingDescription => 'Missing description',
            self::DuplicateTitle => 'Duplicate title',
            self::TitleLength => 'Title too long',
            self::DescriptionLength => 'Description length',
            self::MissingImage => 'No share image',
            self::SmallImage => 'Small share image',
            self::Hidden => 'Hidden from search',
            self::SitemapOff => 'Sitemap is off',
            self::DefaultsNotSet => 'Search defaults not set',
        };
    }

    /**
     * The role of the record's issues it counts, for the Records screen's filter, or null for a site-wide kind.
     */
    public function role(): ?string
    {
        return match ($this) {
            self::MissingTitle, self::DuplicateTitle, self::TitleLength => 'title',
            self::MissingDescription, self::DescriptionLength => 'description',
            self::MissingImage, self::SmallImage => 'image',
            self::Hidden => 'discoverable',
            self::SitemapOff, self::DefaultsNotSet => null,
        };
    }

    /**
     * Whether it is about the whole site and not one record.
     */
    public function siteWide(): bool
    {
        return self::SitemapOff === $this || self::DefaultsNotSet === $this;
    }

    public function tier(): Tier
    {
        return match (true) {
            in_array($this, self::HIGH, strict: true) => Tier::High,
            in_array($this, self::MEDIUM, strict: true) => Tier::Medium,
            default => Tier::Low,
        };
    }
}

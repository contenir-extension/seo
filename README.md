# contenir-extension/seo

[![Continuous Integration](https://github.com/contenir-extension/seo/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir-extension/seo/actions/workflows/continuous-integration.yml)

The SEO add-on for Contenir CMS. It sets what search engines and share previews see: page titles, the default
description and share image, site verification, the site's owner as structured data, the XML sitemap and robots.txt.
It also keeps staging and local copies of a site out of search results.

It replaces the CMS's built-in Sitemap and Site verification sections, and keeps their settings keys, so the public
site reads the same values.

## What it adds

**Three settings sections** under Site Settings > SEO, beside Tracking Codes.

Search Appearance (`seo`):

| Field | Stored under | Default |
|---|---|---|
| Site name | `extensions.seo.site_name` | blank: the name under Site (`site.title`) |
| Separator | `extensions.seo.title_separator` | `\|` |
| Site name in titles | `extensions.seo.title_position`: after, before or none | after |
| Home page title | `extensions.seo.home_title` | blank: built like any other |
| Default description | `extensions.seo.description` | blank |
| Default share image | `extensions.seo.share_image`, uploaded to `/asset/img/seo` | none |
| Share image description | `extensions.seo.share_image_alt` | blank |
| Search engines may | `extensions.seo.robots` | index and follow |
| Organisation name, type and logo | `extensions.seo.organisation_name`, `_type`, `_logo` | the site name, Organisation, none |
| Search engines (this environment) | `extensions.seo.visibility`, in local settings | Automatic |

Site Verification (`seo-verification`):

| Field | Stored under |
|---|---|
| Google Search Console | `google.site_verification`, the CMS's key |
| Bing Webmaster Tools | `extensions.seo.verify_bing` |
| Pinterest | `extensions.seo.verify_pinterest` |
| Meta (Facebook and Instagram) | `extensions.seo.verify_meta` |

Sitemap (`seo-sitemap`), with a "View XML sitemap" link:

| Field | Stored under | Default |
|---|---|---|
| Publish a sitemap | `site.sitemap.enabled`, the CMS's key | on |
| Leave out these workflows | `extensions.seo.sitemap_exclude` | none |
| Extra robots.txt rules | `extensions.seo.robots_rules` | none |

Each verification token, the workflow names and the robots.txt rules are checked as they are saved, by the add-on's
field types (`seo:google-verification`, `seo:bing-verification`, `seo:pinterest-verification`,
`seo:meta-verification`, `seo:workflows`, `seo:robots-rules`). A pasted meta tag is stored as its token.

**Search engines on each environment** (SEO-3). The choice is kept in the environment's local settings. Automatic
hides every copy of the site that isn't at the site URL, so staging and local copies stay out of search results
without anyone saving anything, and production never drops out by accident. A hidden copy sends
`X-Robots-Tag: noindex, nofollow`, and its robots.txt disallows everything.

**Two checks** in the update check:

| Check | Warns when | Fix |
|---|---|---|
| Search engines and this environment | production is set to Hide | one click: set to Automatic |
| | a copy other than production is set to Show | guided: set to Automatic |
| Site URL for search engines | the site has no site URL, so Automatic can't tell production from a copy | guided: where to set it |

The environment is the CMS's `APPLICATION_ENV`. A CMS that names none is taken to be production.

**On public pages**, sites read the settings through contenir/contenir-seo's adapters. On Laminas MVC that is
[contenir/contenir-seo-laminas-mvc](https://github.com/contenir/contenir-seo-laminas-mvc): `/sitemap.xml`, `/robots.txt`,
`$this->seo()->head()` and `X-Robots-Tag`. [contenir/contenir-seo](https://github.com/contenir/contenir-seo) describes the
title template, head tags, JSON-LD, robots.txt and sitemap.

**Per-record titles and descriptions** stay in each site's own `meta_title` and `meta_description` columns (SEO-1).
A per-record SEO field and checks on save come in v2.

It needs no listeners, handlers, tables, scripts or other add-ons. It stores no personal data. The organisation's
profile links are read from the Social add-on's `social.social_*` keys as plain data, so neither add-on needs the
other.

## Requirements

- PHP 8.3, 8.4 or 8.5
- A CMS that hosts contenir/extension-api 0.5

## Installing

```bash
composer require contenir-extension/seo
```

Then enable it in the site's `config/autoload/extensions.global.php`:

```php
return ['extensions' => ['enabled' => ['seo']]];
```

---

## Notes for later add-ons

This add-on follows [the Tracking add-on's guide](https://github.com/contenir-extension/tracking#building-an-add-on).
What it found along the way:

- **A section's name must be the handle or start with `<handle>-`.** The conformance case refuses anything else, so
  an add-on that takes over a core section can't keep that section's name, and so its ACL resource
  (`settings.<category>.<name>`) changes. The host's cut-over has to copy the old section's grants to the new one.
  This add-on's Site Verification and Sitemap are `seo-verification` and `seo-sitemap` for that reason. Their keys
  are unchanged.
- **More than one section.** Declare each with `Registry::settingsSection()`, and read each with
  `$context->settings('<name>')`. With no name, `settings()` reads the section named after the handle.
- **Core settings the add-on doesn't own.** The API has no way to read the CMS's own settings, such as the site URL.
  `SiteSettingsFiles` reads them from the site's settings files, `config/autoload/settings.global.php` with
  `settings.local.php` over it, as the public site does.
- **The in-memory host doesn't run field types.** Test `validate()` and `normalise()` in unit tests. On the
  in-memory host, `set()` stores what it is given.
- **Links on a settings screen.** A `Contribution` of type `link` in the `layout-nav` region is drawn beside the
  screen's buttons. Its privilege (`view` here) decides who sees it.

## Development

```bash
composer cs-check          # mago format --check, lint, guard
composer static-analysis   # mago analyze
composer test              # unit suite
composer test-integration  # conformance, settings files and the in-memory host
composer mutation-test     # infection
vendor/bin/extension-boundary   # after changing require
```

CI pins Mago 1.52.0.

## Licence

BSD-3-Clause. See [LICENSE.md](LICENSE.md).

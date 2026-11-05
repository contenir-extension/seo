# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- An SEO area in the CMS. Its Overview counts each described section's records with something to fix, by title,
  description, image and hidden from search, and shows how the site's own defaults stand. Its Records screen lists
  those records, filtered by section and issue, leading to each one's edit screen and page, and exports them as CSV.
  It reads each record's own columns, so what a site builds in its code, such as a description from a page's blocks,
  isn't counted. A role other than an administrator needs granting the new `seo` resource to see it.
- Dashboard checks for the records (`records`), the site's description, share image and organisation logo
  (`defaults`), and the sitemap (`sitemap`).
- Sections declare themselves to search with an `<seo>` block in admin.xml's `<extensions>`, read as the site reads
  it (contenir/contenir-seo's section map): each child names a role and its columns, first non-empty first, with
  `alt`, `required="true"` and `<advanced>`. The add-on asks the host for the columns the block names that the section
  has.
- `check-meta`: the save check a block binds to its section's title, description and image columns. An editor saving
  a record is told of a missing title, description or image, a title search results would cut off once Search
  Appearance's template adds the site name (60 characters), a description under 70 or over 160 characters, and a
  title another record of the section already has. Each is a warning, unless the block marks the role `required`,
  when a missing value holds the save. It reads the values being saved, so a save never waits on the site.
- "Search and social" (panel `search`), beside a saved record of a section with an `<seo>` block: its title and
  description as search results show them, with their lengths, whether its Discoverable switch hides it, what to
  fix, and once published, links to the page and to Google's Rich Results Test. A site binds it per section with
  `<panels><panel extension="seo" name="search"/></panels>`.
- The `sections` check reports a block without a type, on a section without pages of its own, or naming a column the
  section has no field for.

### Fixed

- A Mezzio site's shared settings are read from the top level of settings.global.php, where the site keeps them, as
  the CMS reads them since 2.0's settings fix. The add-on read only a `settings` key there, so on Studio Esteta it saw
  no site title or URL.

### Changed

- Requires contenir/contenir-extension-api `^0.8`, for section blocks, and contenir/contenir-seo `^0.7`, for its
  section map's reading of a block.

## [0.1.2] - 2026-11-04

### Changed

- Accepts contenir/contenir-extension-api 0.8 as well as 0.7, so the CMS can host 0.8. 0.8's changes are
  additions for add-ons, and only hosts must implement the new methods.

## [0.1.1] - 2026-10-21

### Changed

- Accepts contenir/contenir-extension-api 0.7 as well as 0.6, so the CMS can host 0.7. 0.7's changes are
  additions for add-ons, and only hosts must implement the new methods.
- Requires the API by its current name, contenir/contenir-extension-api, where it was contenir/extension-api.
- Gives its name for people as "SEO" (`extra.contenir.name`), which the CMS's Extensions screen shows in place
  of "Seo".

## [0.1.0] - 2026-10-21

### Changed

- Allows contenir/extension-api 0.6, whose changes (services add-ons provide, and their context in settings
  operations) are additions the add-on doesn't use, so the CMS can install it beside the Cloudflare add-on. The lock
  moves to 0.6.0.
- Requires the library as contenir/contenir-seo `^0.1`, its new name, where it required contenir/seo `0.1.x-dev`. The
  library was renamed after its repository and its v0.1.0 recut, so the old name no longer installs. It comes through
  a `vcs` repository entry until it is on Packagist.

### Added

- Search Appearance (Site Settings > SEO, section `seo`): the site name, separator, where the site name goes in titles
  and a home page title; the default description, share image (1200 × 630) with its description, and what search
  engines may do; the site's owner (name, Organisation or Local business, logo); and "Search engines" for this
  environment, kept in local settings: Automatic (the default), Hide or Show (SEO-3).
- Site Verification (`seo-verification`): tokens for Google Search Console (still `google.site_verification`), Bing,
  Pinterest and Meta, in place of the CMS's own section.
- Sitemap (`seo-sitemap`): the switch (still `site.sitemap.enabled`), workflows to leave out and extra robots.txt
  rules, with a "View XML sitemap" link, in place of the CMS's own section.
- Field types that check verification tokens, workflow names and robots.txt rules as they are saved
  (`seo:google-verification`, `seo:bing-verification`, `seo:pinterest-verification`, `seo:meta-verification`,
  `seo:workflows`, `seo:robots-rules`). A pasted meta tag is stored as its token.
- Two checks: "Search engines and this environment", which warns when production is hidden (with a one-click fix)
  or a copy is shown (with a guided fix), and "Site URL for search engines", which warns when the site has no site
  URL.

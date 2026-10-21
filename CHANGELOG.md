# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased] - 2026-10-21

### Changed

- Allows contenir/extension-api 0.6, whose changes (services add-ons provide, and their context in settings
  operations) are additions the add-on doesn't use, so the CMS can install it beside the Cloudflare add-on. The lock
  moves to 0.6.0.

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

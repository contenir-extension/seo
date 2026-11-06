# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.5.0] - 2026-11-06

### Added

- A Strategy tab beside Overview and Records. It lists each section with its schema type, where its title, description and
  image are read from, and whether what a record's Section field gives is taken automatically or only suggested. A
  section's form changes the schema type, the ordered sources of each role, the alt text's source, "From content" for a
  description or image, and the mode, and saves them into the section's `<seo>` block in admin.xml through the host
  (extension API 0.9.3). Where the site doesn't save its configuration, the tab reads it and says where to change it.
  Overview's section rows link to it, and the screens that save need the Update privilege.
- `<seo mode="manual">` makes what a Section field gives only a suggestion: the checks keep asking for a description and
  image, and the bulk "Write descriptions" can write the suggestion as the record's own. `content="false"` on a role
  stops that role taking it. Requires `contenir-seo` ^0.9 and `contenir-seo-mezzio` ^0.12 on the site.
- Overview's section rows link to the Strategy tab.

### Changed

- Records is drawn with the host's own list (extension API 0.9.3), so its boxes, Select all, row actions and pagination
  match the Library's. Ticking rows shows the bulk fixes; "Fix all" in the header fixes everything the filters match.
- Overview and Records have the standard page header, holding their actions and a line on what to do on the screen.
- The record panel shows how the record reads in search, and says nothing when nothing needs fixing.
- A description or image a record's Section field gives (the first text block's words, the first media block's image) counts
  as given: the checks don't ask for one, the record panel says "Taken from the content" and shows the description in its
  search preview, and bulk "Write descriptions" can save it as the record's own where no summary column gives one.
  Requires `contenir-seo` ^0.9 and `contenir-seo-mezzio` ^0.12 on the site.
- Mutation testing now has to cover and kill every mutant, with `min-msi` and `min-covered-msi` at 100 in CI, as the
  extension API does. Several equivalent code paths were simplified for it: a bulk fix's "holds now" is always nothing
  where a description is written, the Records list no longer carries an unused first-row number, and the Bulk run store
  keeps its newest-first order as it saves.
- A record's View page shows the "Search and social" field as labelled lines (Title, Description, Share image description,
  Canonical address, Robots, Schema type) instead of the stored JSON, with an info label "From content" on a description
  the record doesn't hold but its Section field gives. Requires extension API 0.9.3.
- Description length is a low-impact issue, since a long one is only cut off and doesn't affect ranking.

## [0.4.0] - 2026-11-06

### Added

- A **Use each record's search settings** checkbox in Search Appearance, under This environment. It is the master
  switch for what the site's `<seo>` blocks drive (`extensions.seo.records`), saved for the environment only and off
  until turned on. The Overview says when it is off, and links to it.

### Changed

- The Overview names each section as the site's admin.xml does ("Site Pages", "Projects"), the heading the host gives
  through `LinkableSection::$heading`; a host that gives none gets the records' name as a plural ("Pages"). It also
  says what to do with the counts: select one to see those records, and fix them there. The Records report and the
  dashboard check name sections the same way.
- A site whose sections declare no `<seo>` block no longer has the site-wide defaults check: the check is skipped
  and left off the Overview, so such a site sees no new warning.
- What the site last rendered for a record now goes out of date when the records switch changes, not only when the
  record's columns do, and **Check with the site now** asks again about every record whose answer is five minutes old
  or more (an ordinary round still waits a day). Before, a record whose row was unchanged kept its old answer, so the
  Overview and Records went on reporting what the site had rendered under the other setting.
- Requires `contenir/contenir-seo` ^0.8 and `contenir/contenir-extension-api` ^0.9.1.

## [0.3.0] - 2026-11-05

### Changed

- `seo:advanced` keeps a record's meta title and description, drawn first in its group. The save check, report, panel and
  preview read them there before a block's columns, and put their notes on that field. A site without the field keeps
  its columns, and nothing changes for it. This reverses SEO-1.

## [0.2.1] - 2026-11-05

### Added

- The SEO area's Overview links to the Search Appearance, Site Verification and Sitemap settings.
- A `<seo>` block may name a linked record's column, as `assets[type=hero].path`. The sections check accepts these, and
  a save doesn't ask for a value only the site can find, leaving that to what the site last rendered.
- The report and panel warn when the file a page's share image was made from is smaller than 1200×630, which a share
  variant would otherwise hide. Only for images in the site's public folder, once the site has been asked again.

### Changed

- The "Search and social" panel goes by what the site last rendered for a record, as the report does, while the
  record's columns haven't changed since. It reads the kept answer, so an edit screen never waits on the site, and
  otherwise goes by the record's columns.

## [0.2.0] - 2026-11-05

### Added

- An SEO area in the CMS. Its Overview counts each described section's records with something to fix, by title,
  description, image and hidden from search, and shows how the site's own defaults stand. Its Records screen lists
  those records, filtered by section and issue, leading to each one's edit screen, preview and page, and exports them
  as CSV. It covers published records. A role other than an administrator needs granting the new `seo` resource to see
  it.
- The report goes by what the site renders. A scheduled task, `inspect-records`, asks each published record's page
  through the site's `/api/seo/inspect` every 15 minutes, keeping answers in `data/cache`, and "Check with the site now"
  runs a round on demand. An answer stands for a day, or until the record's columns change. A record the site hasn't
  answered for is judged by its own columns, which miss what a site builds in its code. Needs a host that lends
  `Site\SiteRequestsInterface`, and contenir/contenir-seo-mezzio's endpoint on the site, with any Basic Auth on a
  staging copy exempting it.
- A Preview screen shows one record's search result, share card and structured data as the site renders it now, or its
  own columns, marked as approximate, when the site can't be asked. The "Search and social" panel links to it.
- `seo:advanced`, a field for a record's own SEO overrides, kept as JSON in one column: its share image's description,
  canonical address, robots rule and schema type. Its title, description and image come from fields of its own. It
  draws as a group of inputs, and checks the address, robots rule and schema type as they're saved. The section's table
  needs the column, and the site's entity needs to map it, or the site won't read it. A section uses it by naming the column in `<advanced>`. Needs a CMS that
  marks a field for its type's dashboard component.
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

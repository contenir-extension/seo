# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [0.7.1] - 2026-11-11

### Fixed

- A strategy saved from the Strategy screen that named a field the section's `<seo>` block in admin.xml did not, such as a page's linked `images`, made the site refuse every read of the section, so the dashboard meter, the Overview and the `inspect-records` schedule failed until the strategy was reset. The site now keeps the strategy itself, as an override of the section's block, and reads the fields it names. (contenir/contenir-cms-legacy#426)

### Changed

- The Strategy screen saves and resets through the site (`SectionsInterface::override()` and `restore()`), and no longer writes the hidden `seo-strategy` setting. Strategies already saved there are handed to the site the first time the add-on reads its blocks, and the setting is emptied; one the site won't take stays in the setting, is logged, and is tried again. Needs a host and `contenir/contenir-extension-api` with block overrides.
- A section the site won't lend the columns of reads as having no records, so the other sections are still reported, and the "Sections described for search" check names it and why. Saving a record goes ahead without the duplicate-title warning where the other records' titles can't be read.

### Removed

- `StrategyOverrides`, `StrategyXml` and `StrategyNotSaved`.

## [0.7.0] - 2026-11-11

### Changed

- The "Records need attention for search" alert counts only records that are shown to search and have a High or Medium issue: a missing title or description, a title another record has, a title too long, or no share image. Advice graded Low (description length, a small share image) no longer raises it, and neither does a record hidden from search. Each record counts once, however many issues it has. (contenir/contenir-cms-legacy#425)
- Records opens on the records the alert counts, so the list, "Fix all" and the alert give the same number. Records with only advice, and hidden records, are reached through the Issue and Problem filters, and "Fix all" and Export CSV then act on what is filtered.
- On the Overview, each section's "Fix" count is the number of its records the alert counts; it used to add the title, description and image counts, so a record with two issues was counted twice.
- A record hidden from search is listed only as hidden. Its other gaps leave the To do list, the meter and the score, and still show in the record's own SEO panel.
- A title is a duplicate only when another record shown to search has it. Hidden records are left out of the comparison in the report and in the warning on save, and saving a hidden record raises no duplicate warning. Drafts still count on save.
- A section whose `<seo>` block names no image column is not asked for a share image when the site's page is checked, as it already wasn't when its columns are.

### Added

- A record counts as hidden from search when its Discoverable switch is off, when its own robots setting holds `noindex` or `none`, or when the page the site last rendered for it sent `noindex` in its robots tag or `X-Robots-Tag` header. The message names which. A copy of the site hidden as a whole, or a robots tag that only repeats the site's own setting, does not hide its records.
- A section whose block names no `<discoverable>` column takes `visible` as its switch where it has that field, in the report, the record's panel, the save check and the "Hide from search" and "Show in search" fixes alike.

### Fixed

- "Hide from search" and "Show in search" read the switch as the report does: a record that holds nothing in it is shown, so Hide now turns it off and Show leaves it alone. They used to treat it as already hidden.

### Upgrading

- The report now reads each section's switch column, so what the site last rendered is asked for again once for the sections that gained it.

## [0.6.0] - 2026-11-09

Needs `contenir/contenir-extension-api` 0.9.5.

### Added

- Records lists each issue with a coloured High, Medium or Low badge, and Edit comes first among a row's actions. Needs a host that draws record list badges (extension API 0.9.5); on an older host the lines show plain.
- A Settings group in the SEO menu leading to Search appearance, Site verification, Sitemap and Sections.
- A site can change how a section is described on any environment, with the schema type under Advanced and a "Use the site's defaults" way back. A section changed this way is marked "Changed here".

### Changed

- The Overview is redone: the overall rank across the top with each section beneath, a To do list with a Fix button beside each item that opens its edit form, and Site setup. The dashboard meter's legend names each count by impact, so "Low 2" reads as two low-impact issues.
- Overview, Records and Sections are in the SEO section menu in place of the tab row, and Strategy is called Sections, with its columns and schema types in plain words.
- Site name, Separator and Site name position share one row. The meta description grows with its text. Automatic is described plainly as showing only the live site to search engines.
- The Sections a site has changed are kept in its local settings file, in a hidden `seo-strategy` settings section, instead of `data/seo-strategy.json`. A save the settings can't accept is reported on the form with the reason given. Anything saved to the old file is not carried over (nothing was released with it).

## [0.5.2] - 2026-11-07

### Added

- The Strategy screen and its save each have a permission of their own, "SEO > Strategy > Strategy" and "SEO > Strategy > Strategy save", for a role that may read how sections are described but not change it, or the reverse. They are in `config/admin.xml` as hidden sections of the SEO area, so they don't appear in the menu. **Breaking for roles:** a role granted the SEO area no longer opens or saves a strategy until it is also granted these two (an administrator is unaffected). Needs a CMS that keeps a hidden section out of the menus (contenir-cms-legacy 2.4.3); on an older CMS the two entries show in the area's menu.

## [0.5.1] - 2026-11-07

### Added

- "Use description as its own" in the record panel's Actions menu, for a record whose description is taken from its
  Section field. It opens a page showing the text, and Save writes it into the record's own description column exactly as
  the Records screen's "Write descriptions" does, through the host's record updates. If the record's content changed
  since the page was opened, nothing is written and the new text is shown. It needs the Update privilege.

### Changed

- On a record's View page, a description taken from content reads "Suggested from content" in a manual section, where the page doesn't use it, rather than "From content". A section whose description role is `content="false"` shows none. This needs a host with extension API 0.9.4, which tells the field its section; with an older host the line reads as before. Requires `contenir-extension-api` ^0.9.4.

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

# SEO, sharing and sitemaps

Every public page can be found, indexed and shared well, and no page that must not be public can leak through a search engine, a sitemap or a feed.

## What it does

- Gives every public profile a stable URL, a page title, a meta description, Open Graph and share-card tags, and structured data.
- Publishes a sitemap and robots.txt built from the same visibility rule as pages and search.
- Marks pending-review profiles noindex and leaves them out of every index surface.
- Answers 410 Gone with noindex for removed profiles and drops them from the sitemap.
- Serves 404, 410 and 500 pages that help the visitor.

## Who uses it and how

- As a search engine, I index reviewed profiles, stories and core pages, and skip everything marked noindex.
- As a person sharing a profile or story, I get a clean card with the right title, image and description.
- As an Editor, I can set the SEO title and description on a story.
- As an Administrator, I can rely on a test that proves no non-public profile appears on any surface.

## The pieces

### Theme

- Page `<title>`, meta description, canonical URL and Open Graph and share-card tags on every template.
- Share previews for profiles (name, title, organisation, portrait or logo) and stories.
- Error pages: 404 (not found), 410 (removed, with links to the directory and the complaints policy), 500.
- Responsive images in modern formats (REQ-042).

### Plugin

- The guard plugin emits all SEO markup, sitemap, robots and structured data (schema.org Person and Organization), so no other plugin can add an entry for a non-public profile. There is no SEO plugin.
- Sitemap generated from the public read model: core pages, reviewed profiles, published stories.
- State-aware headers: `X-Robots-Tag: noindex` for pending profiles, 410 and noindex for removed ones, read from the database not a cache.
- Cache purge jobs on unpublish, removal, suppression and approval.
- Closes core surfaces that could leak a profile: feeds, REST, oEmbed, shortlinks, attachment and author pages, user sitemaps, previews.

### Pages and screens

- Public: `/sitemap.xml`, `robots.txt`, 404, 410, 500 pages.
- Staff: SEO title and description fields on stories; Purge log.

### Third-party plugins

None for SEO by design. A CDN may cache public pages (at most 120 seconds, 60 for lists and search) once one is added.

### Data

- Story SEO fields. No separate SEO tables.

## Business rules

- Public content is server-rendered or static so it is indexable, with canonical URLs and a sitemap (REQ-044).
- A Published (review pending) profile returns 200 with `X-Robots-Tag: noindex` and a robots meta tag, and has no sitemap entry, feed entry or structured data, including anything a plugin generates. robots.txt must not block these URLs, because a crawler has to read the page to see the rule. On approval, noindex is removed, markup and sitemap entry are added, and the cache is purged in the same step (REQ-217).
- Removal, suppression, nomination removal and bulk unpublish return 410 with noindex, leave the sitemap and search, and are purged (REQ-209).
- Lists show reviewed profiles only, so a pending name is not indexed through a list (REQ-218).
- Social links are shown as links only, with no social-platform API use (REQ-082).
- The site is English only, handles diacritics, uses NGN formatting and the WAT time zone (REQ-053).
- robots.txt allows all and points to the sitemap; 404/500 pages, robots.txt and sitemap.xml exist (REQ-121).

## Acceptance criteria

- [ ] Every public profile and story has a unique title, meta description, canonical URL and Open Graph tags.
- [ ] Profile pages output valid schema.org Person or Organization markup; pending-review profiles output none.
- [ ] The sitemap lists reviewed profiles, published stories and core pages only.
- [ ] A pending-review profile is absent from the sitemap, feeds, REST, oEmbed and shortlinks, and its page carries the noindex header and meta tag.
- [ ] robots.txt does not disallow pending-review URLs.
- [ ] Approving a pending profile removes noindex, adds the sitemap entry and markup, and purges the cache in one action.
- [ ] A removed profile returns 410 with `X-Robots-Tag: noindex`, disappears from the sitemap and search, and a purge job is queued.
- [ ] Restoring a reversible unpublish returns 200 again.
- [ ] A test builds a profile in every state and requests it on every surface listed above, and passes only if each response matches the state table.
- [ ] Sharing a profile URL produces a card with the person's name, title and image, with no trust label or Sponsored label.
- [ ] No plugin adds an author sitemap, user enumeration or a feed entry for a non-public profile.
- [ ] 404, 410 and 500 pages render with navigation and no technical detail.

## Depends on

- Directory and profiles (states and read models).
- Corrections, removals and reports; Import and sources (state changes that trigger purges).
- Stories (SEO fields).

## Out of scope or later

- Local or multi-language SEO; the site is English only.
- Structured data for awards and events.
- Search-engine submission tooling and rank tracking.

## Requirement ids covered

REQ-006, 042, 044, 053, 082, 121, 209, 217, 218, 247. All public pages indexable and the sitemap live by 31 Jan 2027 (REQ-103).

## Events

A published event (`wiw_event`) has Event structured data (name, start, end, location or online, organiser, image) that matches the visible details, and appears in the sitemap and share previews. A draft, scheduled or removed event has none (REQ-262, REQ-264).

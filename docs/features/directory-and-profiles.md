# Directory and profiles

The directory is the core of the platform: public profiles of people, entrepreneurs, businesses and institutions, each fact shown with its source, browsable by list and filter.

## What it does

- Publishes a profile page for every public person, business and institution, with a stable URL.
- Lists them in five lists (All, People, Entrepreneurs, Businesses, Institutions) with filters, sort and a per-list search box.
- Shows a source and retrieval date beside each key fact, and hides facts that have none.
- Gives every profile four public actions: claim, suggest a correction, request removal, report.
- Applies the publication states so that only profiles that are allowed to be public are shown, on every surface.

## Who uses it and how

- As a visitor, I can browse the directory by industry, country, city, profession or organisation type and share a filtered view by its address.
- As a visitor, I can open a profile and see where each fact came from.
- As a visitor, I can send an enquiry to a profile owner without seeing their email or phone.
- As a Reviewer, I can see which profiles are public and what visitors would see for a profile that is not.
- As an Editor, I can edit a profile in wp-admin with a reason, and the public page changes at once.

## The pieces

### Theme

- Templates: directory list (with tabs and filter panel), profile page for each type, industries index and industry page.
- Profile anatomy: header (photo or logo, name, title, organisation, place, industry), award panel, About, type sections (ventures, mandate and history, career, education, milestones, leadership, products and services, achievements, media), key facts and sources, related stories, right rail (contact, "Something wrong?" actions, share preview).
- No cover or banner image on any profile (REQ-247). The only image is the portrait or logo.
- The pending-review label component and its states on the page, on cards and in share previews.
- Responsive to 360 px, WCAG 2.1 AA, Largest Contentful Paint 2.5 s or better on a mid-range phone over throttled 4G (REQ-040, 041, 043).

### Plugin

- `Directory` module: content types, taxonomies, the `PublicProfile` read model that every template reads through.
- State machine that owns publication state (see "Business rules"). Only it writes the state.
- Field allowlist per profile type, so no field outside the allowlist can be stored (REQ-139, 140).
- Per-field provenance: source URL, retrieval date, confidence, method (REQ-142).
- Enquiry relay form with rate limits and a bot challenge (REQ-018).
- Daily view counts for the staff report only, bots filtered, no IP stored.

### Pages and screens

- Public: `/directory`, `/people`, `/entrepreneurs`, `/businesses`, `/institutions`, `/industries`, `/industries/:name`, `/profile/:slug`.
- Staff: Profiles list (filter and group by source, batch, type, state, origin), Profile screen (edit with reason, fields and their sources, revision history, internal notes, public URL, contact switches), Review queue.

### Third-party plugins

None for the directory itself. There is no SEO plugin; the guard plugin writes all SEO output so that no plugin can expose a non-public profile.

### Data

- Profile content type, with taxonomies for industry, sector, profession, organisation type and place (countries with cities beneath).
- Tables for state, versions, field provenance and view counts. See the build data model.

## Business rules

- A profile has exactly one publication state: Imported (not public), Published (review pending), Published (reviewed), Unpublished, Rejected, Removed and suppressed. Only the two Published states are public (REQ-208, 210).
- Individuals and entrepreneurs are public only after a human approves them. Organisations can be public on import with the label "Compiled automatically from public sources. Human review pending." and a noindex rule (REQ-216, 217).
- Lists, the home page and industry pages show reviewed profiles only, so a pending name cannot be indexed through a list. Pending profiles can be found by direct URL and on-site search, always with the label (REQ-218).
- Facts with no source or low confidence cannot be published (REQ-143). Individual profiles hold professional facts only: name, current title and organisation, industry, public-record roles, published awards, official website (REQ-139).
- Never stored for individuals: home address, phone, personal email, family, date of birth or age, health, religion, politics, ethnicity, finances, allegations, personal social handles (REQ-140). Nobody under 18 holds a profile (REQ-068).
- Profiles have no trust label and are never Sponsored or Featured. Staff see a Claimed checkmark; the public does not (REQ-150, 155).
- A profile's contact details are private by default; nothing public beyond the enquiry form unless staff switch it on at the owner's request (REQ-060).
- Stub profiles show no photograph unless owner-supplied or licensed (REQ-162).
- Changing a slug creates a redirect from the old URL.

## Acceptance criteria

- [ ] Each of the five lists loads, shows 16 profiles per page, and applies filters from the URL.
- [ ] Filters offered: industry, country, city on all lists; profession on People and Entrepreneurs; organisation type on Businesses and Institutions.
- [ ] A filtered list URL, opened in a new browser, shows the same filtered result.
- [ ] A profile in every one of the six states, requested on the page, lists, search, sitemap, feed, REST, oEmbed and previews, returns the response the state table says (200, 404 or 410) and appears only where allowed.
- [ ] A Published (review pending) profile returns 200 with an `X-Robots-Tag: noindex` header and a robots meta tag, shows the label, and has no sitemap, feed or structured-data entry.
- [ ] A pending profile never appears on a list, the home page or an industry page, but appears in search with its label.
- [ ] Every key fact on a profile page shows a source and a retrieval date; a fact without a source is not rendered.
- [ ] A profile page never renders an email address or phone number for an individual.
- [ ] No profile page renders a cover or banner image.
- [ ] Editing a published profile requires a reason, creates a version with author and time, and writes an audit row.
- [ ] Changing a slug makes the old URL redirect.
- [ ] Profile and list pages pass an automated accessibility check at 360, 768 and 1280 px with no horizontal scroll.
- [ ] The enquiry form is refused after repeated sends from one IP or email, and refused by the bot challenge when unsolved.

## Depends on

- Import and sources (records arrive there).
- Roles, audit and admin (capabilities, audit log).
- SEO, sharing and sitemaps (markup and sitemap).
- Corrections, removals and reports; Claims (the four profile actions).

## Out of scope or later

- Profile analytics beyond a view count (REQ-020).
- A right-of-reply note on a profile (REQ-172, should-have).
- An "include pending" view on lists (dropped).
- Owner login or self-edit. There are no public accounts.

## Requirement ids covered

REQ-001, 003, 004, 005, 006, 007, 009, 018, 040, 041, 043, 060, 068, 123, 125, 139, 140, 142, 143, 144, 156, 162, 208, 209, 210, 216, 217, 218, 226, 247. Milestone: profile templates and directory structure by 20 Nov 2026 (REQ-098).

# SEO and sharing

This page sets out what a public page tells search engines and social platforms: titles, descriptions, canonical addresses, share cards, structured data, the sitemap and the noindex rules. The visibility rule is the same everywhere, so a page that is not public says nothing to anyone.

What the requirements fix is marked "required". Where the documents are silent, the entry is a proposal and says so.

## Who emits what

There is no SEO plugin. The must-use guard plugin emits every SEO tag, the sitemap and the robots rules itself, so nothing can add a sitemap line, feed entry or structured data for a profile that is not public. It also closes the other leaks: core sitemaps, feeds, REST, oEmbed, author and attachment pages, previews and `?p=ID` short links.

The theme supplies the visible parts (share image, the card text, the pending label) and the template slots. It does not write meta tags by hand.

## Visibility decides everything

| State | HTTP | Robots | Sitemap | Structured data | Share card |
|---|---|---|---|---|---|
| Published, reviewed | 200 | Indexable | Yes | Yes | Yes |
| Published, review pending (organisations) | 200 | `X-Robots-Tag: noindex` header and a robots meta tag | No | None, including from plugins | Yes, with the pending label |
| Unpublished | 410 (200 again if restored) | noindex | No | None | None |
| Removed and suppressed | 410, permanent | noindex | No | None | None |
| Draft, rejected, hidden | 404 for visitors | n/a | No | None | None |

Required by REQ-006, REQ-209, REQ-217. Two details matter. A pending page returns 200, not 404, because it is reachable by link and search. And `robots.txt` must not block pending addresses, because a crawler has to read the page to see the noindex rule.

The state is read from the database on every request, not from a cache, so a takedown takes effect at once. On approval the noindex goes, and the markup and sitemap entry are added.

## Titles and descriptions

Required: every published profile has a page title and meta description (REQ-006). The patterns below are proposals.

| Page | Title pattern | Description source |
|---|---|---|
| Profile | Name, title or industry, brand name | First sentence of the biography, else a line built from title, organisation and city |
| Directory list | List name, brand name | A fixed sentence per list |
| Industry | Industry name, brand name | A fixed sentence plus the count |
| Story | Headline, brand name | The lede |
| Awards pages | Page name, edition name | A fixed sentence per page |
| Nominee | Nominee name, category, edition | The citation. Never names the nominator |
| Editor pages | The page title, brand name | A field on the page, with a fallback to its first paragraph |
| Search, done, error pages | Plain title, brand name | None |

The brand name and edition come from theme configuration ([Theme settings](/theme/theme-settings)), never typed into a template. The name is WHOISWHO and stays one value.

## Canonical addresses

Required: canonical URLs are provided (REQ-044). Proposals for how:

- Every indexable page has one `rel=canonical` pointing at its own clean address.
- Filtered and sorted list views point their canonical at the unfiltered list, since filters live in the address and would otherwise create many near-duplicates.
- A changed slug redirects from the old address to the new one (core behaviour). The canonical always names the current slug.
- Paginated lists carry their own canonical per page.

## Share previews

Required: Open Graph tags on every published profile (REQ-006). The prototype's "When shared" panel on a profile is an illustration of this card, not a feature of the live page.

| Part of the card | Source |
|---|---|
| Image | The profile's photograph or logo. A profile has no cover image (REQ-247), so a profile without either uses a generic brand card |
| Title | Name |
| Line under it | Brand name, then title or industry |
| Pending label | If the profile is pending review, the label "Compiled automatically from public sources. Human review pending." appears in the card text (REQ-216) |

Proposals for the rest: stories use their cover image, headline and lede, and a Sponsored story states "Sponsored" in the card text. Awards pages use a brand card with the edition name. Nominee cards use the portrait and citation and never name the nominator.

The same label rule covers the page, any card and the share preview. A pending profile can be shared, but it can never be shared looking like a reviewed one.

## Structured data

| Type | Required | Notes |
|---|---|---|
| Person (individuals, entrepreneurs) | Yes (REQ-006) | Only allowlisted professional fields: name, current title and organisation, industry, public-record roles, published awards, official website (REQ-139). Never an email, phone, address, age or any forbidden attribute (REQ-140). |
| Organization (businesses, institutions) | Yes (REQ-006) | Public facts only, each of which has a source. |
| Pending-review profiles | None | Absent entirely, including anything a plugin would add (REQ-217). |
| Article for stories | Proposal | Headline, date, author, image. A Sponsored story should say so. |
| FAQ markup | Proposal | Optional; only on the FAQ page, and only if the team decides to use it. |
| Breadcrumbs | Proposal | On profile, story and award pages. |

A published event page (`/events/:slug`) also carries Event structured data (name, start, end, location or online, organiser, image) that matches the visible details (REQ-262).

A test checks that no structured data exists on any page that is not public.

## Sitemap

Required: `sitemap.xml` and `robots.txt` exist (REQ-121). The sitemap is generated from the same public read model as the pages and search, so it cannot disagree with them (REQ-208).

| In the sitemap | Not in the sitemap |
|---|---|
| Core pages, every reviewed public profile, every published story, the public awards pages | Pending-review profiles, unpublished, removed or suppressed profiles, drafts, scheduled posts, anything hidden |

When a profile is removed, bulk unpublished, suppressed or a nomination is set Not live or removed, its line leaves the sitemap in the same step (REQ-209). After a removal, staff also ask search engines to drop the already indexed address; meanwhile the 410 and noindex are already in force.

## Pages that should not be indexed

Required: pending-review profiles and every 410. The rest are proposals.

- Search results.
- Done and confirmation pages (`/done`, `/nominate/done`, `/invited/done`).
- Pages reached by a single-use link: `/confirm/:token`, `/invite/:token`, `/consent/:token`, `/expired`.
- Staff previews of unpublished pages.
- The claim, correction, removal and data-rights forms.

## Performance and crawling

Public pages are server rendered, so crawlers read the full page without running scripts (REQ-044). Images are served resized and in modern formats (REQ-042). Profile and directory pages should reach a Largest Contentful Paint of 2.5 seconds or better on a mid-range Android phone over throttled 4G (REQ-041, an assumed target).

## Tests

| Test | Asserts |
|---|---|
| State matrix | One profile per state is requested on every surface: page, lists, search, sitemap, core sitemaps, feeds, REST, oEmbed and short links. Only the two public states appear, and only reviewed profiles are in the sitemap. |
| Markup | Pending pages have noindex and no structured data. Reviewed pages have the right type and no forbidden field. |
| 410 | A removed profile returns 410 with noindex, leaves the sitemap and search, and its cache entry is purged. |
| Robots | `robots.txt` allows pending addresses. |

## Open points

- Title and description patterns, canonical rules and the extra structured data types are proposals, to confirm when the theme shell is built.
- Share image for profiles with no photograph or logo.
- Whether to publish FAQ markup.

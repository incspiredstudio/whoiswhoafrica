# Feature catalogue

Everything we aim to build, one row per feature. Each feature page says what it does, who uses it, which parts of the build it touches, the rules, and testable acceptance criteria. Use these pages to cut work into issues.

The build has our own code in three places, plus maintained third-party plugins:

| Part | What it is |
|---|---|
| Theme | The `whoiswho` theme: templates, components, motion, tokens |
| Plugin | `whoiswho-core` (domain modules) and the `whoiswho-guard` must-use plugin |
| Pages and screens | Public pages and WordPress admin screens |
| Third-party | Maintained plugins: wpDiscuz, a two-factor plugin, Akismet, a job runner, and others still to choose |

Priority: M is must-have (built as early as possible), S is should-have. Dates in the brief are ceilings, not targets.

## Catalogue

| Feature | What it does | Pri | Touches | Brief date | Depends on |
|---|---|---|---|---|---|
| [Directory and profiles](./directory-and-profiles) | Public lists, filters and profile pages with a source beside every fact, and the publication states | M | Theme, Plugin, Pages | Templates by 20 Nov 2026 | Import, Roles, SEO |
| [Search](./search) | One search across public profiles and stories, never ordered by payment | M | Theme, Plugin, Pages | 31 Jan 2027 | Directory, Stories |
| [Claims](./claims) | Claim a profile with no account; staff decide in 5 days with reminders; Claimed mark for staff only | M | Theme, Plugin, Pages | 31 Jan 2027 | Directory, Roles, Email |
| [Corrections, removals and reports](./corrections-removals-reports) | Public forms, the Corrections and Requests and reports screens, deadlines, suppression list, 410 takedown and bulk unpublish | M | Theme, Plugin, Pages, Third-party | Support process by 20 Dec 2026 | Directory, Email, SEO |
| [Stories](./stories) | Magazine-style stories, types, Featured and Sponsored labels, with paid placement slots | M | Theme, Plugin, Pages | 31 Jan 2027 | Directory, Commercial |
| [Events](./events) | Events as their own content type: list and pages, header and footer links, Home block, related-story link, registration link, Event structured data | M | Theme, Plugin, Pages | 31 Jan 2027 | Stories, Home, SEO |
| [Comments](./comments) | Moderated comments on stories through wpDiscuz | S | Theme, Plugin, Third-party | 31 Jan 2027 | Stories, Privacy |
| [Newsletter](./newsletter) | Email sign-up for new stories, with opt-in and one-click unsubscribe | M | Theme, Plugin, Pages | 31 Jan 2027 | Stories, Email, Privacy |
| [Awards and nominations](./awards-and-nominations) | Rules, categories, nominations behind one toggle, stage and Live status, nominees and winners (judging is offline) | M (behind the Nominations flag) | Theme, Plugin, Pages | 31 Jan 2027 | Directory, Corrections, Email |
| [Home and curation](./home-and-curation) | Home page and the modes (Latest, Random, Manual, Pinned then fill) that pick profiles and stories | M home, S curation | Theme, Plugin, Pages | Home viewable by 20 Nov 2026 | Directory, Stories, Awards |
| [Get listed and enquiries](./get-listed-enquiries) | Enquiry form that creates an Enquiries item, contact form, profile enquiries, invitation and consent links | M | Theme, Plugin, Pages | Customers by 20 Dec 2026 | Commercial, Email |
| [Import and sources](./import-and-sources) | Import with dry run, states on import, per-field sources, duplicates | M | Plugin, Pages, Third-party | Directory populated by 31 Jan 2027 | Directory, Roles |
| [Sponsored stories and deals](./sponsored-content-and-commercial-register) | Sponsored story deals in four steps, counting rule, the labelled slot, independence from ranking | M | Theme, Plugin, Pages | Register and reporting by 20 Dec 2026 | Stories, Get listed, Roles |
| [Privacy and data rights](./privacy-and-data-rights) | Consent, cookies, data requests, retention, processor register, breach procedure | M | Theme, Plugin, Pages | Terms and Privacy by 21 Nov 2026 | Roles, Email |
| [SEO, sharing and sitemaps](./seo-sharing-and-sitemaps) | Titles, share cards, structured data, sitemap, noindex and 410 rules | M | Theme, Plugin | 31 Jan 2027 | Directory, Corrections |
| [Email and notifications](./email-and-notifications) | Every email the site sends, the log, SPF, DKIM, DMARC, reminders | M | Theme, Plugin, Pages | 31 Jan 2027 | Roles |
| [Roles, audit and admin](./roles-audit-and-admin) | Staff roles, capabilities, two-factor, dashboard, audit log, exports | M | Plugin, Pages, Third-party | Reporting by 20 Dec 2026 | All |
| [Editable pages and copy](./editable-pages-and-copy) | Page copy edited in a page editor; FAQ block; brand tokens | S | Theme, Pages, Third-party | 31 Jan 2027 | Home, Awards, Privacy |
| [Platform quality](./platform-quality) | Performance, accessibility, backups, monitoring, staging and tests | M | Theme, Plugin, Third-party | 31 Jan 2027 | All |

## Build order that follows from the dependencies

1. **Foundations:** Roles, audit and admin; Platform quality (staging, CI, backups); Email and notifications; the theme tokens.
2. **The record:** Directory and profiles; Import and sources; SEO and sitemaps; Corrections, removals and reports.
3. **Reading and finding:** Search; Stories; Events; Home and curation; Newsletter. Events follow Stories because a story can link one, and come before Home so its Events block has something to show.
4. **Trust and revenue:** Claims; Get listed and enquiries; Sponsored stories and deals; Privacy and data rights.
5. **Recognition:** Awards and nominations (the flag stays off until the Venture CEO approves); Comments; Editable pages and copy.

The build plan will turn each row into a GitHub milestone, and each acceptance checklist item into an issue or a test.

## Rules that every feature shares

- No public accounts. Staff only have accounts (REQ-229).
- A profile is public only in its two Published states; this holds on every surface (REQ-208).
- Profiles are never Sponsored or Featured. Only stories are. There are no Verified profiles; staff see a Claimed mark.
- Payment never affects nominations, judging, winners, profile ranking, search ranking or labels. It can only buy a labelled story placement slot (REQ-183, 184, 199, 263).
- Every staff action on records, labels, arrangements, imports and capabilities is audited (REQ-051, 188).
- No payment processing, card data or gateway anywhere.

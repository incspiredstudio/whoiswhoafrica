# Templates

This page lists every public template the `whoiswho` theme must provide: its address, the data it reads, and the states it has to handle. It is the checklist for building the theme and the contract between the theme and the plugin.

The template names below are proposals. The requirements fix the behaviour, not the file names. Route ids (V01, V09 and so on) point at the prototype screens that show the intended look. Requirement ids are cited as plain text.

## Rules every template follows

| Rule | Detail |
|---|---|
| Read models only | Templates never call `get_post_meta` or `WP_Query` on the profile type. They read profiles through `PublicProfile` and stories through `PublicStory`. A read model returns data only when the state allows it and only allowlisted fields (REQ-208, REQ-139). |
| No state logic in the theme | The theme never decides whether a profile is public. It renders what the read model returns, or the 404 or 410 the plugin tells it to render. |
| No brand values in templates | Colours, fonts, spacing, the brand name, tagline and edition come from the tokens file and theme settings ([Brand and tokens](/theme/brand-and-tokens), [Theme settings](/theme/theme-settings)). |
| No accounts | No template shows sign in, sign up, a profile menu or a saved list. Every action works without an account ([The public site](/pages/public/)). |
| Server rendered | Every page is readable and indexable without JavaScript. Motion and enhancements are added on top (REQ-044). |
| One image on a profile | The only image on a profile is the photograph or logo. No cover or banner (REQ-247). |
| Labels | Only stories can show Sponsored. Profiles show no trust label. Claimed is never shown publicly (REQ-150, REQ-155). |
| Markup comes from the plugin | Structured data, canonical, robots and share tags are emitted by the guard plugin so nothing can add them for a page that is not public ([SEO and sharing](/theme/seo-and-sharing)). |

## Shared template parts

| Part | Used by | Notes |
|---|---|---|
| Header | Every page | Five groups (Directory, Awards, Stories, Events, About), a search icon and a "Get listed" button. Events is a plain link to `/events`. The Nominate links are hidden while the Nominations switch is off (REQ-259). A drawer on phones. Transparent over the home hero only. |
| Footer | Every page | An Events link, trust and legal links, the Cookie preferences button, brand line. |
| Cookie notice | Every page | A small card after a little scroll, Decline and Accept. Analytics load only after Accept (REQ-062, REQ-248). |
| Page band | Inner pages | Eyebrow, title and lede, with an optional tone colour. |
| Profile card and story card | Lists, home, search, related blocks | Profile card: poster, industry, name, role. Story card: 16:9 image, type, date, headline, lede, author. |
| Form field and bot check | Every public form | Label, input and hint; a bot challenge; a privacy notice at the point of collection (REQ-058, REQ-122). |
| Notices | Several | Pending-review label, Sponsored notice, draft note, staff preview banner. |
| Done page | After every form | Title, a sentence, a reference and a way back. |

## Home and directory

| Page | Address | Template | Reads | States to handle |
|---|---|---|---|---|
| Home (V01) | `/` | `front-page` | Curated profile and story blocks through the read models (REQ-257), the next three upcoming events through `PublicEvent`, published nominees | Nominees block absent (not empty) before nominees are published. Events block absent when no event is upcoming. No awards band; the marquee carries the category names. Static page when scripts or motion are off. |
| Directory lists (V52, V03, V53, V04, V05) | `/directory`, `/people`, `/entrepreneurs`, `/businesses`, `/institutions` | `directory` with a type argument | `PublicProfile` list queries: reviewed profiles only, filtered, sorted, 16 per page | No results; active filter chips; filters kept in the address; a profile pending review is never listed (REQ-218). |
| Industries (V06) | `/industries` | `industries` | Industry terms with public counts | An industry with no public profiles. |
| Industry page (V07) | `/industries/:name` | `industry` | Profiles and stories in the sector | No profiles; no stories. |

## Profile

| Page | Address | Template | Reads | States to handle |
|---|---|---|---|---|
| Profile (V09 to V11, V54, V56) | `/profile/:slug` | `profile` | One `PublicProfile`, its facts with sources, its published nominations, linked `PublicStory` items | See the list below. |

States the profile template must handle:

| State | Result |
|---|---|
| Published, reviewed | Full page, indexable (200). |
| Published, review pending (organisations only) | 200 with the label "Compiled automatically from public sources. Human review pending.", a link to correct or remove it, noindex, no schema markup (REQ-216, REQ-217). |
| Unpublished, removed or suppressed | 410 Gone with noindex (REQ-209). |
| Draft, rejected, hidden | 404 for visitors. Staff see a preview banner. |
| Old slug | Redirect to the current address. |
| Claimed | The Claim button is not rendered (REQ-251, REQ-252). |
| Pending review | The prototype still shows Claim here, but a pending profile cannot be claimed (REQ-226). Decide in the build whether to hide the button. |
| Enquiries switched off | The enquiry form is replaced by a plain statement. |
| No photograph | The coloured poster and mark stand in. |
| Section has no content | The section is not rendered. |
| Fact without a source | The fact is not shown (REQ-133, REQ-144). |

## Search and stories

The single story template shows a Related event card at the end of the story when the story links an event and that event is published (REQ-265). The Events templates are `archive-event` (`/events`: an orange header band with a Next up block, the shared filter bar, then agenda rows grouped by month, upcoming first and past muted below) and `single-event` (`/events/:slug`: a poster header with a large date, a sticky details card beside the text, Related stories as a plain list, and More events as agenda rows). Both read `PublicEvent` and neither reuses the story card or article templates (REQ-264).

| Page | Address | Template | Reads | States to handle |
|---|---|---|---|---|
| Search (V08, V38) | `/search` | `search` | The public search index across profiles and stories (REQ-254) | Empty box; results grouped Profiles then Stories with a count; no results with a link to the directory. |
| Stories (V16) | `/stories` | `stories` | `PublicStory` list, filtered by type and industry | No hero. Mosaic, grid and sign-up block. No Events filter and no upcoming strip. "No stories match" with a clear-filters action. Sponsored label on sponsored cards, and a labelled slot for an active placement. |
| Story (V17) | `/stories/:slug` | `single-story` | One `PublicStory`, linked public profiles, three other stories, the comment thread | Not published returns 404 to visitors and a banner to staff. Sponsored notice. Linked profile that is not public is omitted. Comments held for moderation (REQ-258). |

## Awards and nominations

The awards read through the plugin's awards view-models. Their names are not yet defined; the theme builds against fixtures until they are.

| Page | Address | Template | States to handle |
|---|---|---|---|
| Overview (V18) | `/awards` | `awards` | Stage timeline and main button follow the stage: switch on, nominees published, winners published, nothing published. Sponsors block only when sponsors exist. |
| Category (V19) | `/awards/category/:i` | `award-category` | No public nominees yet; winner shown only when winners are published. |
| Nominees (V55) | `/awards/nominees` | `award-nominees` | "Nominees are not published yet" before publication. Group tabs and counts. |
| Nominee (V20) | `/awards/nominee/:ref` | `award-nominee` | Not public returns 404 to visitors, preview banner to staff. The nominator is never named. |
| Winners (V21) | `/awards/winners` | `award-winners` | Filters in the address; twelve per page grouped by year; confetti once, skipped for reduced motion (REQ-246). |
| Rules (V33) | `/awards/rules` | page | Draft note until governance text arrives. |
| Nominate (V22) | `/nominate` | `nominate` | Switch off: "Nominations are closed" with links, no form and no sign-up. Switch on: the six-step form. Guidance appears in both (REQ-250, REQ-259). |
| Nomination steps | `/nominate/verify`, `/nominate/done` | `nominate-step` | Expired or used link; too many nominations from one device; duplicate warning; contact details or sensitive words blocked in evidence. |

## Forms, requests and single-use links

| Page | Address | Template | States to handle |
|---|---|---|---|
| Claim (V57) | `/claim/:slug` | `claim` | Already claimed shows "Already claimed" and a link to suggest a correction. Unknown slug returns 404. |
| Correct (V39) | `/correct/:slug` | `correct` | Rate limit; email confirmation link sent for a person or their representative. |
| Remove (V40) | `/remove/:slug` | `remove` | Same; promise of two business days for a valid request. |
| Report | dialog on a profile | `report` (part) | Reason and details; lands in the Support inbox. |
| Done (V41) | `/done?kind=` | `done` | Kinds: correction, removal, claim, contact, enquiry and newsletter. The nomination confirmation is its own page (N04). |
| Confirm, expired | `/confirm/:token`, `/expired` | `confirm`, `expired` | Single use; "Link expired or already used". |
| Get listed (V24) | `/subscribe` | `get-listed` | Enquiry form, no prices or packages (REQ-177, REQ-120). |
| Contact (V26) | `/contact` | `contact` | Form; side links to common alternatives. |
| Invitation and consent | `/invite/:token`, `/consent/:token` | `invite`, `consent` | Draft held against the link; uploads checked; expired link asks for a new one. |
| Data rights (V50) | `/data-rights` | `data-rights` | Request types, reference, due date. |
| Unsubscribe (V45) | `/unsubscribe` | `unsubscribe` | One click, then confirmation. |

## Pages edited in the page editor

About (V02), FAQ (V28), Awards rules, Complaints (V35), Privacy, Terms, Cookie policy, and the guidance blocks on Nominate use a generic `page` template with editable blocks ([Blocks and patterns](/theme/blocks-and-patterns)). Legal pages carry a draft note until Legal supplies the text.

## System pages

| Page | Address | Notes |
|---|---|---|
| Not found (V13) | any | 404 with search and directory links. |
| Gone (V12) | removed profile | 410 with an explanation and links to the directory and the complaints policy. No reason shown. |
| Server error (V14) | any | 500, plain text, no technical detail (REQ-121). |
| Sitemap | `/sitemap.xml` | Built from the public read model; see [SEO and sharing](/theme/seo-and-sharing). |
| Robots | `/robots.txt` | Allows all and names the sitemap. It must not block pending-review addresses (REQ-217). |

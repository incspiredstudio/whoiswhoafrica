# Blocks and patterns

This page says what staff edit in the page editor and what stays in code. The rule is that wording is edited by staff and structure, logic and brand values are fixed by the theme.

Page copy is edited in the native block editor, not in code (REQ-260, D-060). The theme supplies block patterns, so staff edit content and the theme owns structure. There is no page builder.

## The split

| Edited by staff in the editor | Fixed in the theme |
|---|---|
| The words on About, FAQ, Awards rules, Complaints, the legal pages and the Nominate guidance | Header, footer, cookie notice and the menus' structure |
| The order and number of FAQ groups and questions | Every form, its fields, its bot check and its privacy notice |
| The home page's text lines and calls to action | The profile and story templates and their states |
| Page titles, descriptions and share images for editor pages | Colours, fonts, spacing and motion (the tokens file) |
| Draft notes, which are removed when copy is final | Which button shows for which awards stage |
| Images from the media library for editor pages | Whether a page is public, and every label |

Staff never type a status, a label or a number that the system knows. Counts, the edition name and the stage come from data.

## Patterns the theme supplies

A pattern is a ready-made arrangement of blocks that an editor inserts and then edits. These are proposals based on the prototype's pages.

| Pattern | Used on | Editable parts |
|---|---|---|
| Page hero | About, FAQ, legal | Eyebrow, headline, lede |
| Purpose band | About | The purpose statement |
| Pull statement and prose | About | A pull quote and two or three paragraphs |
| Three pillars | About | Three titles and sentences. Colours are fixed to the brand blocks |
| Link list ("Three ways into the record") | About | Titles, descriptions and the pages they link to |
| Check list ("Built to be checked") | About | Three short titled points |
| Figures | About | Nothing. Counts are calculated (see dynamic blocks) |
| Closing call | About, home, FAQ | Heading and up to two buttons |
| Legal section | Privacy, Terms, Cookies, Complaints | Heading and paragraphs, with an optional draft note |
| Guidance section | Nominate | Heading and text for How it works, Eligibility, What to have ready, Short answers |

## The FAQ block

The FAQ is a repeatable question-and-answer block with groups (REQ-260). Each group has a heading and one or more questions. Each question has a title and a rich answer that can include links.

| Rule | Detail |
|---|---|
| Structure | Group, then question, then answer. The first question opens; the rest are collapsed. |
| Links | Answers can link to pages by choosing them, so a renamed page does not break the link. |
| Promises | Answers that state a deadline (acknowledge in one business day, resolve corrections in seven, hide a valid removal in two, decide a claim in five) must match the real process. Whoever changes a deadline in settings changes the FAQ in the same step. |
| Order | Groups and questions are dragged into order. |
| Structured data | FAQ markup is emitted only if the team decides to, and only on this page ([SEO and sharing](/theme/seo-and-sharing)). |

The current groups in the prototype are Being listed, Accuracy and removal, Awards, Stories and Your data. They are sample copy.

## Dynamic blocks

These blocks pull from the system and cannot be typed over. An editor places them and sets only the few options listed.

| Block | Shows | Options |
|---|---|---|
| Figures | Counts of public profiles, industries, countries and stories | None |
| Awards stage button | One button whose label and link follow the stage | None |
| Awards categories | The groups and categories from the Award categories screen, each linking to its page | None |
| Judging criteria | Fixed page copy edited in the page editor | None |
| Edition label | The edition name from settings | None |
| Profile rail ("On the record") | Profiles chosen by the home curation mode (REQ-257) | Which curation setting to follow |
| Story track | Stories chosen by the stories curation mode | Which curation setting to follow, count |
| Nominees grid | Public nominees, only once published | Group filter on or off |
| Newsletter sign-up | The sign-up form | Heading and sentence |

The Nominate guidance reads its categories from the Award categories screen, so changing a category does not need a page edit. The eligibility and criteria text are page copy. The guidance adds no data collection (REQ-250).

## Locked parts

The following are built in code and are not blocks:

- The hero videos, the three colour scenes, the scroll exit and every animation ([Motion and performance](/theme/motion-and-performance)).
- Forms: claim, correct, remove, report, contact, get listed, data rights, nomination steps, comments and unsubscribe.
- The pending-review label, the Sponsored notice and the staff preview banner.
- The sitemap, robots file, canonical, robots meta and structured data.

## Who edits

Editing pages needs core page capabilities. The capability list written so far names the story capabilities for Editors and Administrators, and it does not state a default for pages. Treat pages as Editor and Administrator by default and confirm it when the capability list is written for the build. A Reviewer does not edit pages unless the Administrator grants it. Home and stories curation is a separate capability, `wiw_curate_home`, which Editors and Administrators hold by default (REQ-257).

## Draft copy

Legal pages, the rules, About and the FAQ are all draft in the prototype. Each carries a visible "draft" note. The notes are blocks the editor deletes when Legal or Brand supplies the final text, so nobody edits the template to remove them. Final mission, vision and values come from Brand; legal wording comes from Legal; governance and conflict-of-interest text for the rules comes from the Venture CEO.

## Open points

| Point | State |
|---|---|
| Native block editor or Elementor | Decided: native block editor with theme patterns (D-060). |
| Default role for editing pages | Not written down. Proposed: Editor and Administrator. |
| FAQ structured data | Not decided. |
| Names and exact set of patterns | Proposed here, to be settled when the theme shell is built. |

## Event pieces

The theme supplies an Event agenda row pattern (date block, title, place and organiser, status tag, arrow; a compact form for Home and a form with a small image for the Events page), an Event poster header, an Event details card, a Next up block, a Related event card for the end of a story, a Related stories list for the Event page, and the Home Events block (next three upcoming as compact rows plus "All events"). They read from `PublicEvent` and `PublicStory`; staff do not edit their structure (REQ-262, 264, 265).

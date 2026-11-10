# Home

The home page tells a visitor what the platform is and sends them to the directory, the awards and the stories. It is the most designed page on the site and the only one built around motion.

Route `/` (V01). Requirements: REQ-001, REQ-002, REQ-106, REQ-195, REQ-248, REQ-264 (requirements).

## What a visitor sees, in order

| Block | What it does |
|---|---|
| Hero | The headline "Who is who in Africa." over four muted, looping, autoplaying videos side by side. Hovering one widens it and raises its contrast and saturation, with no zoom. On phones the four become a 2 by 2 grid. Scrolling out of the hero shrinks the video panel inward so background shows on the sides and bottom, then the next section follows. |
| Three acts | Three pinned full-screen scenes: Influence, Achievement and Legacy, each with a brand mark and a link (people, awards, stories). These are the three words that organise the brand. |
| On the record | A list of profiles. On desktop, hovering a name shows a floating card: the person's photo if there is one, otherwise a coloured card with the brand symbol. On phones and tablets it becomes a rail of posters that advances by itself and can be swiped. A link opens the directory. |
| Four doors | Large links to People, Entrepreneurs, Businesses and Institutions. |
| Awards marquee | A slow, endless text strip whose words are the award categories. It is a strip only: no heading, no statement, no numbered category list and no button. The Awards link lives in the header and the awards pages |
| Nominees | A grid of up to eight nominee posters with group filters. Shown only after nominees are published. On phones the filters are hidden. |
| Stories from the record | A horizontal track of the six latest stories. A placement set to Home or Both fills a labelled slot here without changing the order of the others (REQ-263). |
| Events | The next three upcoming events as compact agenda rows (date block, title, place and organiser, status tag), and an "All events" link to `/events`. The whole block is absent when no event is upcoming (REQ-264). Events are never Sponsored |
| Closing call | "Who should the continent know?" with one button. |

The edition is named "WHOISWHO in Africa: The Maiden Edition" (D-001).

## Rules

**Only reviewed profiles appear.** Profiles that are pending review never appear on the home page, because a name on an indexable list could be found by search engines before a person has checked it (REQ-218). The label explainer on the page therefore uses a generic specimen.

**Nothing paid influences it.** The On the record list is not picked by any label or arrangement. In the prototype the rule is fixed: people with a portrait first, then organisations, eight in total. Stories are the six newest. Events are the next three upcoming, soonest first.

**Nominees follow publishing.** The nominees block appears only once staff publish nominees (REQ-198). Before then it is absent, not empty.

**Motion respects settings.** Motion is reduced or off for visitors who ask for reduced motion, and the page works as a complete static page without its scripts.

## Awards on the home page

The home page no longer has an awards band with a heading, a statement and a stage-aware button. The award categories run in the marquee, and the nominees block appears once nominees are published (REQ-198). The stage still decides what the Awards pages and the Nominate link show (REQ-194, REQ-259, D-059). See [Awards](/pages/public/awards).

## How curation will work in WordPress

The prototype's fixed rules are a placeholder. REQ-257 (D-059) defines the real behaviour: each block that lists profiles or stories has a mode set on a Home page settings screen.

| Mode | Meaning |
|---|---|
| Latest | The newest eligible items |
| Random | A random selection from eligible items, fixed for the day and cached |
| Manual | Items an editor picks and orders |
| Pinned then fill | Manual picks first, remaining slots filled by Latest or Random |

Eligible means public, reviewed and not suppressed. Items without a portrait are skipped where a block needs one. The recommended start is On the record as pinned then fill with Random, Stories as Featured first then Latest, and Nominees and winners automatic. The Events block is automatic (the next three upcoming events) and is not curated. Editors and Administrators hold the capability `wiw_curate_home` by default, and the Administrator can grant it to any role. The screen is not drawn in the prototype yet.

## Open points

- The hero videos and the Repline font came from the team's downloads folder and have no confirmed licence.
- The home page text is sample copy until Brand supplies final wording.
- The stock portraits (Freepik) are placeholders and must be replaced.

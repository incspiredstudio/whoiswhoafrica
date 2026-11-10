# Home and curation

The home page tells a visitor what the platform is and sends them to the directory, awards and stories. Editors choose how its profile and story blocks pick their items.

## What it does

- States what the brand is, who it is for and how to get listed, nominated or recognised, within the first screen on a phone.
- Presents the hero, the three acts (Influence, Achievement, Legacy), On the record, the four doors, the awards marquee, nominees, stories, events and a closing call.
- Lets staff set, per block, whether items are the latest, random, hand-picked, or pinned then filled.
- Applies the same modes to the stories page. Only stories are chosen there. A sponsored placement set to Home or Both fills a labelled slot in the stories block, and never changes the order of the other items (REQ-263).
- The Events block shows the next three upcoming events automatically, with an "All events" link. It is not curated and is absent when no event is upcoming (REQ-264).

## Who uses it and how

- As a visitor, I see a page that says what WHOISWHO is and where to go next.
- As a visitor on a phone, I see the four hero videos as a 2 by 2 grid and a rail of profiles that advances by itself.
- As an Editor, I can pin profiles or stories to the home page and choose how the rest are filled.
- As an Administrator, I can grant the curation capability to another role, such as Reviewer.

## The pieces

### Theme

- Home template with blocks: hero (four muted looping videos that widen on hover without zoom, scroll-exit shrink on desktop), three pinned acts, On the record (floating hover card on desktop showing the photo or a coloured symbol card; autoscrolling rail on phones), four doors, awards marquee (the category names as endless text; no heading, statement or button), nominees grid (hidden filters on phones, shown only after nominees are published), stories track, events block (next three upcoming), closing call.
- The page works as a complete static page without scripts. Motion is reduced for reduced-motion visitors.
- Cookie notice card that appears after a little scrolling (REQ-248).
- Site header and drawer behaviour on the home page, with no flash of the wrong header on load.

### Plugin

- `Content` module: a Home page settings screen with one setting per block: mode, item count, pinned item IDs.
- Eligibility query through the `PublicProfile` and `PublicStory` read models.
- Daily seed for Random, with the result cached; saving the settings purges the cache.
- Capability `wiw_curate_home` (Editor and Administrator by default, grantable to any role). Every change is audited.

### Pages and screens

- Public: `/`.
- Staff: Home page settings (not yet drawn in the wireframe).

### Third-party plugins

None. Videos and fonts are theme assets whose licences must be confirmed before launch.

### Data

- Block settings in WordPress options. Pinned IDs reference profiles or stories.

## Business rules

- Modes: Latest (newest eligible), Random (a random selection of eligible items, fixed for the day and cached so every eligible item gets a turn), Manual (editor picks and orders), Pinned then fill (manual picks first, remaining slots filled by Latest or Random) (REQ-257).
- Eligible means public, reviewed and not suppressed. Items without a portrait are skipped where a block needs one.
- A payment, arrangement or label never makes an item eligible or ranks it (REQ-183).
- Pending-review profiles never appear on the home page (REQ-218).
- Recommended starting setup: On the record is Pinned then fill with Random; Stories is Featured first then Latest; Nominees and winners are automatic from the published awards with no choice.
- The nominees block appears only once nominees are published. The red awards band with the edition title and the numbered category list is not on the home page.
- The edition is named "WHOISWHO in Africa: The Maiden Edition".
- Brand name, colours and tagline come from the theme tokens file, not the page (REQ-052, 190).

## Acceptance criteria

- [ ] The home page's first screen on a 360 px phone states what the brand is and shows a way to get listed, nominate or browse.
- [ ] The hero shows four videos that autoplay muted and loop; hovering one widens it with no scale change; on phones they form a 2 by 2 grid.
- [ ] With reduced motion requested, hero motion, the rail's autoscroll and scroll effects are off and the page still reads in full.
- [ ] On the record shows no pending-review profile, whatever the mode.
- [ ] In Latest mode the block shows the newest eligible items in order.
- [ ] In Manual mode it shows exactly the chosen items in the chosen order.
- [ ] In Pinned then fill mode the pinned items come first and the rest are filled from Latest or Random.
- [ ] In Random mode the selection stays the same for a day and changes on the next, and is served from cache.
- [ ] Saving the settings screen shows the change on the page without waiting for the cache to expire.
- [ ] Without `wiw_curate_home`, the settings screen returns 403; granting the capability to a Reviewer opens it.
- [ ] Every settings change writes an audit row with actor, block, old and new values.
- [ ] Creating or ending a paid arrangement does not change which items any block shows.
- [ ] The home page has no awards band, numbered category list or awards button; the marquee shows the award categories as text.
- [ ] The Events block shows the next three upcoming events and an "All events" link, and is absent when none is upcoming.
- [ ] The nominees block is absent before nominees are published and present after.
- [ ] There is no header flash on first load or refresh on the home page.

## Depends on

- Directory and profiles; Stories; Awards and nominations (stage).
- Roles, audit and admin (capabilities).
- Editable pages and copy (home page text).

## Out of scope or later

- Personalised or analytics-driven ordering.
- A visual home page builder.
- Letting any label or customer status influence order.

## Requirement ids covered

REQ-001, 002, 052, 190, 218, 248, 257; also REQ-183, 198. Home page and core landing pages viewable by 20 Nov 2026 (REQ-096).

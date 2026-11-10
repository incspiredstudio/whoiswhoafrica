# Stories

Stories are the media side of the platform: interviews, features, spotlights, opinion, announcements and news about the people and ideas on the record, laid out like a modern magazine. Stories are blog articles only. Events are a separate content type (see Events), and a story can link one event to show at its end.

## What it does

- Publishes stories with a type, author, cover image, body, tags and SEO fields, with scheduling and revisions.
- Shows a stories page with a lead mosaic, a three-column grid, filters by type and industry, and sign-up blocks.
- Links stories to profiles and industries, so a profile lists its related stories.
- Marks sponsored stories with a clear label, gives a paid story a labelled placement slot for set dates, and puts Featured stories first.

## Who uses it and how

- As a visitor, I can browse the newest stories and filter by type and industry.
- As a visitor, I can read a story and see the profiles it is about.
- As an Editor, I can write, schedule and publish a story, link it to profiles, and mark it Featured.
- As an Editor, I can record a sponsored placement: the sponsor, the story, where it appears (Home, Stories or Both) and the dates.
- As a sponsor, I can pay offline for a story to be placed in Stories, knowing it is labelled and changes nothing else.

## The pieces

### Theme

- `/stories` template: masthead with type pills and an industry filter, a three-tile mosaic (one large, two small, 16:9 images), a three-column grid of the next six, a dark sign-up block, then more of the grid. There is no hero.
- Story card: type, date, reading time, headline, one-line lede, author. Reading time is word count at about 180 words a minute, with a two-minute minimum.
- `/stories/:slug` template: type, date, industry, optional Sponsored badge, headline, lede, author, wide cover image, body, pull quote, "On the record" profiles, comments, "More stories".
- Sponsored notice inside the story.
- 16:9 cover image crop for tiles and cards.

### Plugin

- `Content` module: story type or native posts with Featured as the sticky flag, link to profiles and industries, the optional `related_event_id` link to one event (REQ-265), and the sponsored placement.
- `PublicStory` read model; stories are read only through it.
- Sponsored placement: a record with sponsor, story, placement and dates. It is scheduled, active or expired by a job. While active the story shows in a labelled slot; afterwards the label and slot go, and nothing is deleted (REQ-116, 263).
- Story entries in the search index and sitemap from the published state only.

### Pages and screens

- Public: `/stories`, `/stories/:slug`.
- Staff: WordPress Posts (core screens), Story sponsors screen. The wireframe does not draw an Articles screen because WordPress provides it.

### Third-party plugins

None for stories. Comments use wpDiscuz (see Comments). The newsletter sign-up is covered in Newsletter.

### Data

- Posts with story fields: type, industry, linked profiles, sponsor, Featured (sticky), hero image, SEO fields.
- Sponsor records (name, logo, link).

## Business rules

- Only stories can be Sponsored or Featured. Profiles never are (REQ-155).
- A paid placement is the only thing money buys. It never reorders unsponsored stories or search, and never touches profiles, nominations or winners (REQ-263, 183).
- The Sponsored label appears on the story page, the card and the share preview, shows the sponsor's name, and is never styled like a trust status (REQ-026, 182).
- The notice in a sponsored story reads: the content is paid for by the sponsor, the sponsor did not write it and has no influence over any label or award.
- Stories are ordered newest first, except that Featured stories come first on the stories page (REQ-257).
- A story that is draft, scheduled or removed returns 404 to visitors and is not searchable or in the sitemap. Staff see a preview banner.
- A linked profile that is not public is not shown on the story.
- A paid arrangement never changes a story's Featured flag (REQ-184).

## Acceptance criteria

- [ ] A story can be created, scheduled and published by a user with the core story capabilities; a user without them cannot.
- [ ] The stories page lists published stories newest first and shows type, date, reading time, headline, lede and author on each card.
- [ ] Filtering by type and by industry narrows the list; a filter with no matches shows "No stories match" and offers to clear filters.
- [ ] A story with a Sponsored label shows the badge and sponsor name on its card, page and share preview.
- [ ] When a placement's end date passes, the label and slot are removed without deleting the story.
- [ ] A sponsored story appears in its labelled slot on the chosen surface (Home, Stories or Both) only between its start and end dates.
- [ ] The order of unsponsored stories and of search results is identical with and without an active placement.
- [ ] Marking a story Sponsored requires `wiw_set_sponsored`; marking it Featured requires `edit_others_posts`.
- [ ] A draft or scheduled story returns 404 to a visitor and is absent from search and the sitemap.
- [ ] A story linked to a public profile shows that profile; linked to a non-public profile it shows nothing for it.
- [ ] A profile page lists the stories that link to it.
- [ ] Cover images in tiles render at a 16:9 crop on desktop and phone.
- [ ] No profile page, card or share preview ever shows Sponsored or Featured.

## Depends on

- Directory and profiles (linked profiles).
- Sponsored content and the commercial register (arrangements).
- Search; SEO, sharing and sitemaps.
- Home and curation (Featured-first ordering).

## Out of scope or later

- A cover-image crop control per story beyond the 16:9 theme crop.
- Story series, authors' pages and categories beyond type and industry.

## Requirement ids covered

REQ-026, 027, 028, 029, 155, 182, 249, 262, 263; also REQ-116, 184. Content and templates ready for production by 31 Jan 2027 (REQ-103).

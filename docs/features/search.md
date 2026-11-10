# Search

One search that finds people, organisations and stories, never returns anything that is not public, and is never ordered by payment.

## What it does

- Offers a search box in the header panel and on a search page. Both use the same results.
- Returns results grouped as Profiles and Stories, with a total count, name matches first.
- Offers a separate narrowing box inside each directory list, which filters only that list.
- Removes a profile or story from results the moment it is unpublished.

## Who uses it and how

- As a visitor, I can type a name, organisation or keyword and see matching profiles and stories on one page.
- As a visitor, I see a clear message when nothing matches, with a button to browse the directory.
- As an Editor, I can unpublish a profile and know it leaves search at once.

## The pieces

### Theme

- Header search panel and the `/search` template.
- Result cards: profile card and story card (a story shows the Sponsored label if it is sponsored). Results are never ordered by payment.
- Empty, results and no-results states.
- Per-list search box beside the filters on each directory list.

### Plugin

- One search index built from public states only, covering profiles and stories (REQ-254). The proposed store is a flattened FULLTEXT table (`wiw_profile_search`).
- Index updates in the same step as publish, unpublish, suppress, bulk unpublish and story publish or unpublish.
- Ranking: name matches first, then relevance. Nothing else influences order.
- Excludes core WordPress search for the profile type.

### Pages and screens

- Public: `/search`, header search panel.
- Staff: the Purge log records removals that affect search.

### Third-party plugins

None decided. Whether core search with filters, a relevance plugin or a hosted service is used is a build question; the sizing target is 10,000 or more profiles (REQ-079).

### Data

- Search table built from the read models. Never contains a non-public item.

## Business rules

- Search follows the same visibility rule as pages and sitemaps (REQ-208). Removed, suppressed, rejected, draft and hidden items are never returned.
- A profile in Published (review pending) can be found, always shown with its label, even though lists exclude it (REQ-216, 218).
- Stories are indexed from their published state only. Drafts, scheduled posts and removed posts are not indexed.
- Results are ordered by relevance and name match. A sponsor, subscriber or customer gets no better position (REQ-183).
- No account is needed. Analytics, if any, load only after consent (REQ-062).

## Acceptance criteria

- [ ] A search for a public person's name returns that profile first in the Profiles group.
- [ ] A search for a word in a published story title returns the story in the Stories group.
- [ ] The result count equals the number of profiles plus stories returned.
- [ ] A draft story, a scheduled story and an unpublished story are never returned.
- [ ] A profile in each non-public state (Imported, Unpublished, Rejected, Removed and suppressed) is never returned.
- [ ] After a profile is unpublished, the next search does not return it, with no cache delay on the origin.
- [ ] A Published (review pending) profile is returned, and its result shows the pending label.
- [ ] A Sponsored story's result shows the Sponsored label.
- [ ] Creating a paid arrangement for a customer does not change any result order (automated test).
- [ ] An empty search shows the prompt text; a search with no matches shows the no-results text and a link to the directory.
- [ ] The header panel and the search page return identical results for the same query.
- [ ] Search on a 10,000-profile dataset returns in a measured time agreed at build (target set in the build plan).

## Depends on

- Directory and profiles (states, read models).
- Stories.
- Roles, audit and admin (the purge log).

## Out of scope or later

- Spelling correction, synonyms and typo tolerance (the prototype does simple substring matching only).
- Search analytics beyond privacy-friendly web analytics.
- Searching comments.

## Requirement ids covered

REQ-008, 079, 208, 209, 254; also REQ-062, 183, 216, 218. Delivered with the directory ahead of the 31 Jan 2027 ceiling (REQ-103).

# Search

Search lets a visitor find a person, organisation or story by typing a name or keyword. One search returns profiles and stories together, and never returns anything that is not public.

Route `/search` (V08, V38). Requirements: REQ-008, REQ-254, REQ-208, REQ-209, REQ-079 (requirements). Decision: D-057 (decisions).

## Where search lives

| Place | Behaviour |
|---|---|
| Header search | A search icon opens a panel. It uses the same results as the search page. |
| Search page | A search box and the full results, with a count and a statement that name matches come first. |
| Directory lists | Each list (All, People, Entrepreneurs, Businesses, Institutions) has its own search box that narrows only that list. It is a filter, not the site search. |

The site search and the list search are different on purpose. The site search crosses types. The list search is a quick narrowing inside one type, and it works together with the filters.

## What the results contain

Results are grouped under two headings, each with its own items:

| Group | Matched on | Order |
|---|---|---|
| Profiles | Name, organisation, title, industry and biography | Profiles whose name matches come first |
| Stories | Title and body | In publishing order |

The result count covers both groups. A profile result looks like a directory card. A story result looks like a story card, with the Sponsored label if the story is sponsored. Payment never changes result order. Events are not in site search at launch; they are reached from the header, the footer, Home and related stories.

**Search is a requirement across both types (REQ-254).** In the WordPress build it is one query against one index that holds profiles and stories, built only from published states. A story that is a draft, scheduled or removed is not indexed.

## Rules

**Nothing non-public appears.** Search follows the same visibility rule as pages and the sitemap (REQ-208). Removed, suppressed, rejected, draft and hidden profiles are never returned. A removal takes a profile out of search at once, along with its cache entry and sitemap line (REQ-209).

**Profiles pending review can appear in search.** A profile with the label "Compiled automatically from public sources. Human review pending." is excluded from lists but can be found by search or a direct link, always with its label. This is the reason the label exists (REQ-216, REQ-218, Q54).

**Search never takes payment into account.** Results are ordered by relevance and name match only. A sponsor, subscriber or customer gets no better position (REQ-183, FAQ answer: "Can I pay for a better position in search or lists?" No).

**No account is needed.** The search box is a plain form. Analytics, if any, load only after consent (REQ-062).

## States

| State | What the visitor sees |
|---|---|
| Empty search box | "Type a name, organisation or keyword", with a note that results show public profiles and stories only |
| Results | A count, then Profiles, then Stories |
| No results | "No results. Nothing public matches that search. Check the spelling or try fewer words." with a button to browse the directory |

## What the prototype does and does not do

The prototype matches text with a simple case-insensitive substring match and puts name matches first. It does not rank by relevance, handle spelling mistakes or index the full text of profiles beyond the fields above. The WordPress build is planned to use a full-text index (`wiw_profile_search`, InnoDB FULLTEXT) covering both types in one query. See Architecture and the search decision record ADR-010.

Search results need a staff counterpart for correctness: when staff publish, unpublish, suppress or bulk unpublish, the index is updated in the same step. The Purge log screen (A48) records the removals that affect search.

## Related

- [Directory and profiles](/pages/public/directory-and-profiles) for lists and filters.
- [Stories](/pages/public/stories) for what is searchable on the stories side.

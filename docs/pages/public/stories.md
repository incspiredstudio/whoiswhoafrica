# Stories

Stories is the media side of the platform: interviews, features, spotlights, opinion, announcements and news about the people and ideas on the record. It is laid out like a modern magazine or newsletter, not like a blog archive.

Routes: `/stories` (V16) and `/stories/:slug` (V17). Requirements: REQ-026 to REQ-029, REQ-182, REQ-249, REQ-258, REQ-263, REQ-265 (requirements). Decisions: D-006, D-055, D-056 (decisions).

## The stories page

The page has no hero. It opens straight into stories, in this order:

1. A masthead with the page title, a plain row of story-type tabs (All, Interview, Feature, Spotlight, Opinion, Announcement, News), and under it the quiet filter bar with an Industry list, the number of stories and Clear while a filter is on. Events are not here; they have their own [Events](/pages/public/events) pages.
2. A mosaic of three stories: one large lead tile and two smaller tiles beside it, all 16:9-style images.
3. A three-column grid of the next six stories.
4. A dark sign-up block: "Get new stories in your inbox."
5. The rest of the stories in the same three-column grid.

A story paid for with a placement set to Stories or Both sits in a labelled slot in the mosaic and grid.

Every card shows the story type, date, reading time, headline, a one-line lede and the author. Reading time is calculated from the word count at about 180 words a minute, with a two minute minimum. Stories are ordered newest first. If a filter matches nothing the page says "No stories match" and offers to clear the filters.

## Newsletter sign-up

The two sign-up forms ask only for an email address. A sign-up records the address and sends a confirmation, and every email carries an unsubscribe link (REQ-061, REQ-249). The visitor lands on a "You are subscribed" page.

In the prototype the sign-up is stored in a simple list with no consent timestamp or double opt-in. A real build needs a Subscribers list with consent date, a confirmation step and an unsubscribe tied to it, and the email service must be added to the processor register. Whether a newsletter is wanted at launch, and who sends it, is still open (D-056).

## A single story

A story page has the story type, date, industry, an optional Sponsored badge, the headline, a lede, the author, a wide cover image, the body, a pull quote where the story has one, and then:

| Block | Shown when |
|---|---|
| Profiles in this story ("On the record") | The story is linked to public profiles. A profile that is not public is not shown, even if linked. |
| Related event | The story links an event (`related_event_id`) and that event is published. A card at the end of the story shows the event's date, title, place and a link to its page (REQ-265). |
| Comments | Always, see below |
| More stories | Always, three other stories |

A story that is not published returns 404 to visitors. Staff opening it see a banner saying it is a draft or scheduled and not public.

## Paid placement and the Sponsored label

A sponsor can pay for a story (never an event) to be placed in a labelled slot on the Home, the Stories page or both, for set dates (REQ-263, D-062). The slot does not reorder the other stories or search results. Payment is recorded offline.

Only stories can be Sponsored (D-055, REQ-155, REQ-182). A sponsored story shows a "Sponsored" badge with the sponsor's name on its card and its page, and a notice in the story: "Sponsored content. Paid for by (sponsor). The sponsor did not write it and has no influence over any label or award."

The label comes from a live placement on the Sponsored stories screen: a slot for that story, within its start and end dates. When the dates end the label and slot go with them. It is never styled like a trust status. The staff side is the Sponsored stories screen. A payment never changes a profile, a nomination or an award.

## Comments

Stories have comments, provided in the WordPress build by the wpDiscuz plugin (REQ-258, D-059). The prototype shows a design illustration with two sample comments and a form.

| Rule | Detail |
|---|---|
| No account | A comment needs a name, an email and a consent tick. The email is never shown and is used to say when the comment is approved. |
| Moderated | Every comment is held for moderation and appears only when approved by someone holding the core moderation capability (Editors and Administrators by default). |
| Spam | A honeypot, rate limits and Akismet or an equivalent. |
| Privacy | Gravatar is off. Notification emails to commenters need an opt-in. Comments are personal data and are covered by the data-rights export and erase. |
| Where | Stories only. Profiles have no comments. |

After posting, the visitor sees "Thank you. Your comment will appear once it is approved." Moderation follows the WordPress defaults (D-060).

## Search and sitemaps

Published stories are searchable ([Search](/pages/public/search)) and appear in the sitemap. Drafts, scheduled posts and removed posts are in neither. Stories are filterable by industry, and an industry page lists its stories next to its profiles.

## Who feeds it

| Content | Fed by |
|---|---|
| Stories | WordPress posts (create, schedule, publish, revisions). The Articles screen is not drawn in the wireframe because WordPress provides it (D-059). |
| Featured stories | The native sticky or featured flag. Featured applies to stories only. |
| Sponsor and Sponsored label | Sponsored stories screen (A18) |
| Newsletter sign-ups | A Subscribers list (to be specified) |
| Home and stories-page selection | The Home page settings and capability `wiw_curate_home` (REQ-257); stories use the same modes as the home page |

## Prototype notes

Story images are placeholders, the stories are fictional and the author "WHOISWHO Stories" is a sample. Cover crops for the 16:9 tiles are set by size in the prototype; the WordPress build needs a cover-image crop on the post.

# Content

The module for everything editorial: stories, events, home page and stories page curation, the newsletter, comment rules and editable pages.

## Responsibility

- Add what stories need on top of WordPress posts: type, linked profiles, an optional linked event, a sponsored placement.
- Provide events as their own post type, `wiw_event`, with their fields and the `PublicEvent` read model.
- Decide which items the home page and the stories page show, by a mode a user can choose.
- Hold the stories newsletter sign-ups as consent records.
- Apply the comment rules to wpDiscuz.
- Make page copy editable without code.

Stories are native WordPress posts. WordPress already provides scheduling, revisions, trash and the Featured (sticky) flag, so the module adds fields and rules, not a second editor.

## Content types, taxonomies and tables

| Item | Notes |
|---|---|
| Stories | Core posts, with a story type (interview, feature, spotlight, opinion, announcement, news), an industry term, linked profiles, a hero image, an optional `related_event_id` and a sponsored placement (sponsor, placement, start, end). Stories are blog articles only |
| Events | Custom post type `wiw_event`: start, end (optional), venue or Online, city, country, organiser, industry term, summary or body, image, registration link, price note. Scheduling, revisions and trash come from WordPress. Never Sponsored, not in search at launch |
| Featured | WordPress's sticky flag. Applies to stories only |
| Pages | Core pages for About, FAQ, Contact, Awards rules, legal pages and the page text of the home page |
| Home page settings | A settings record per block: mode, item count, pinned ids |
| Newsletter sign-ups | Rows in the consent table (purpose: stories newsletter) with a confirmed date. Proposed. The requirements fix the form and unsubscribe, not the sending tool |
| Comments | Stored by wpDiscuz in WordPress's comment tables |

## Admin screens

| Screen | What it does |
|---|---|
| Posts (native) | Create, schedule, publish, unpublish, Feature, trash. The story editor has a Related event field |
| Events | Create, schedule, publish, unpublish and trash events (`wiw_manage_events`) |
| Home page settings | For each block: choose Latest, Random, Manual, or Pinned then fill; set the count; pin and order items; see a preview |
| Stories page curation | Same modes for the stories page. Featured stories first |
| Comments (wpDiscuz) | Moderate, approve, reject, mark spam |
| Pages | Edited in the native block editor |
| Subscribers (proposed) | Count, consent date and unsubscribe status. Export is logged |

## Capabilities

| Capability | Reviewer | Editor | Administrator |
|---|---|---|---|
| `edit_posts`, `edit_others_posts`, `publish_posts`, `edit_published_posts`, `delete_posts` (core) | no | yes | yes |
| `upload_files` (core) | no | yes | yes |
| `wiw_curate_home` | no | yes | yes (grantable to any role, including Reviewer) |
| `wiw_manage_events` | no | yes | yes (grantable to any role, including Reviewer) |
| `wiw_set_sponsored` | no | yes | yes |
| `moderate_comments` (core) | no | yes | yes |

## Public read models and endpoints

| Item | Behaviour |
|---|---|
| `PublicStory` | Returns a story only when published. Exposes type, industry, author, date, linked public profiles, sponsor label |
| `/stories`, `/stories/{slug}` | Grid with filters by type and industry; story page with related profiles and comments |
| Curation blocks | The home page and stories page ask the module for their items: `Curation::items(block)` |
| `POST /newsletter` | Sign-up form: email and a consent tick; sends a confirmation link |
| One-click unsubscribe | Every newsletter message carries it |

## Jobs, hooks and events

| Trigger | Action |
|---|---|
| Saving the Home page settings | Audit the change and purge the cache |
| Daily | Refresh the Random seed for each block set to Random |
| Story published, unpublished or scheduled | Update search, related links and cache |
| Sponsor period ends | Remove the label (Commerce) |
| New comment | Hold for moderation; notify moderators |

## Business rules

### Curation modes (REQ-257)

| Mode | Behaviour |
|---|---|
| Latest | The newest eligible items |
| Random | A random selection of eligible items, fixed for the day and cached, so the page stays fast and every eligible item gets a turn |
| Manual | The items an editor picks, in the order they set |
| Pinned then fill | Manual picks first, then the remaining slots filled by Latest or Random |

- **Eligible means public, reviewed and not suppressed.** The block reads through `PublicProfile` and `PublicStory`, so a hidden or unreviewed item cannot appear.
- **Payment never makes an item eligible or ranks it.**
- **Items without a portrait are skipped** where a block needs a picture.
- **Nominees and winners are automatic** from the published awards. They have no mode.
- **Recommended start:** On the record = Pinned then fill with Random. Stories = Featured first, then Latest.
- **Who:** `wiw_curate_home`, Editor and Administrator by default. The Administrator can grant it to any role.

### Comments (REQ-258)

- wpDiscuz on stories only; none on profiles or pages.
- Guests comment with a name, an email and a consent tick. No account.
- Every comment is held until a user with `moderate_comments` approves it.
- Gravatar is off. Commenter email is never shown. Notification emails to commenters are opt-in.
- Spam: honeypot, rate limit and a spam filter (see [Third-party plugins](/plugins/third-party)).
- Comments are personal data: in the PII catalogue, the export and erase flow, and the processor register.
- Suggested: close comments 90 days after publication, and no comments on Sponsored stories until Legal agrees (open question).

### Newsletter

- Double opt-in. Consent is stored with a timestamp and the policy version.
- Not an enquiry. A sign-up never enters Enquiries or the customer records.
- Unsubscribe in one click, and the unsubscribe page works without an account.

## Tests that must exist

- `test_REQ_257_random_mode_is_stable_for_the_day`
- `test_REQ_257_manual_mode_never_returns_a_hidden_profile`
- `test_REQ_257_pinned_then_fill_keeps_pins_first`
- `test_REQ_257_curation_requires_capability_and_is_audited`
- `test_REQ_258_comment_is_held_until_approved`
- `test_REQ_258_comments_closed_on_profiles_and_pages`
- `test_REQ_061_newsletter_message_has_one_click_unsubscribe`
- `test_REQ_058_newsletter_consent_stores_policy_version`
- `test_REQ_182_sponsored_story_shows_label_on_card_and_share_preview`

## Depends on

Directory (eligible profiles, search), Commerce (the Sponsored label), Compliance (consent records, data requests), Platform (capabilities, cache purge, audit, mail), the theme.

## Requirement ids covered

REQ-027 to 029, 058, 061, 182, 249, 257, 258, 260, 262, 264, 265.

## Events and placements

- An Event is the post type `wiw_event`, not a story type. `PublicEvent` exposes its fields and computes upcoming or past from the end date, or the start date if there is no end. Templates read events only through it (REQ-262, 264).
- The Home page Events block asks `PublicEvent` for the next three upcoming events. The Events list shows upcoming first, then past, filtered by city and industry.
- A story's `related_event_id` is read through `PublicStory`, which returns the linked event only while it is published. `PublicEvent` returns the stories that link to an event, published only (REQ-265).
- Events are not indexed by search at launch. The sitemap lists published events.
- A sponsored placement puts a story (never an event) in a labelled slot on Home, Stories or Both between two dates. It is read through `PublicStory` and never reorders unsponsored stories or search (REQ-263).
- Tests: `test_REQ_262_event_is_past_after_end_date`, `test_REQ_262_draft_event_has_no_structured_data`, `test_REQ_264_events_list_orders_upcoming_first`, `test_REQ_264_home_shows_next_three_upcoming_events`, `test_REQ_265_story_shows_related_event_only_while_published`, `test_REQ_262_events_are_not_in_search`, `test_REQ_263_placement_rejects_an_event`, `test_REQ_263_placement_does_not_reorder_unsponsored_stories`, `test_REQ_263_placement_expires_and_removes_label`, `test_REQ_244_wiw_manage_events_is_grantable`.

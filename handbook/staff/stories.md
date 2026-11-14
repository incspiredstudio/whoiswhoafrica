<!-- verify-after-round-9 -->

# Stories

Stories are articles, kept as ordinary WordPress posts. Staff write, schedule and publish them, mark one Featured, link them to profiles, choose what the Home and Stories pages show, and moderate comments.

## Who does what

| Work | Where | Capability | Default roles |
|---|---|---|---|
| Write, schedule, publish and trash stories | Posts | `edit_posts`, `edit_others_posts`, `publish_posts` | Editor, Administrator |
| Mark a story Featured | The sticky flag on the post | `edit_others_posts` | Editor, Administrator |
| Choose or schedule a Sponsored slot | Sponsored stories | `wiw_set_sponsored` | Editor, Administrator |
| Curate Home and Stories | Directory > Home page settings | `wiw_curate_home` | Editor, Administrator |
| Moderate comments | Comments | `moderate_comments` | Editor, Administrator |

The Administrator can grant `wiw_curate_home` to any role.

## Publish a story

1. Open **Posts > Add New Post**. Enter the title, body, author, hero image, tags and search and sharing fields.
2. Choose the type: interview, feature, spotlight, opinion, announcement or news.
3. Link the profiles and industries the story is about. Each linked public profile appears in the "On the record" block, and the profile page lists the story.
4. Optionally pick a Related event.
5. Publish it, or schedule it. Revisions and trash work as usual.

Published stories are searchable and in the sitemap. Drafts, scheduled posts and removed posts are in neither.

## Featured and Sponsored

| Flag | How it works |
|---|---|
| Featured | An editorial choice using the sticky flag, for stories only. Featured stories come first on the Stories page and Home. Payment never sets it. |
| Sponsored | A visible label with the sponsor's name, for stories only. It comes from a live slot on the Sponsored stories screen. See [Sponsored stories](/staff/sponsored-stories). |

Profiles are never Sponsored or Featured.

## Curate Home and Stories

Each Home block that lists profiles or stories has a mode.

| Mode | What it shows |
|---|---|
| Latest | The newest eligible items |
| Random | A random selection of eligible items, fixed for the day and cached |
| Manual | The items an editor chooses and orders |
| Pinned then fill | Manual picks first, remaining slots filled by Latest or Random |

1. Open **Directory > Home page settings**.
2. For each block (On the record, Stories, Nominees and winners) choose a mode, and pin items if the mode needs them.
3. Save. Saving refreshes the cache so the new picks appear, and the change is audited.

Eligible means public, reviewed and not suppressed. A payment or label never makes an item eligible and never ranks it. Items without a portrait are skipped where a block needs one. Nominees and winners are automatic. The Events block is automatic and shows the next three upcoming events.

A sensible starting setup:

| Block | Mode |
|---|---|
| On the record | Pinned then fill, filling with Random |
| Stories | Featured first, then Latest |
| Nominees and winners | Automatic |

## Comments

Comments use the wpDiscuz plugin, installed from [Required plugins](/start/required-plugins). Only stories take comments. A reader gives a name, an email and ticks the consent box; no account is needed. Every comment is held until a staff member approves it.

### Moderate comments

1. Open **Comments** in the left menu. A number beside it shows how many are waiting.
2. Choose the **Pending** filter.
3. Read each comment. Hover over it to see the actions:
   - **Approve**: it appears under the story.
   - **Spam**: it is hidden and helps the spam filter learn.
   - **Trash**: it is deleted after 30 days.
4. To answer as WHOISWHO, use **Reply** on an approved comment. Your reply appears at once.

Approve a comment only if it is on topic, civil, and makes no allegation, rumour or personal claim about anyone. When in doubt, leave it pending and ask an Editor.

Safeguards are a honeypot, rate limits, spam filtering, no Gravatar and no commenter emails shown. Comments are personal data: a commenter can ask for a copy or for erasure, which [Requests and reports](/staff/requests-and-reports#handle-a-data-request) covers.

wpDiscuz has its own settings menu. WHOISWHO applies the comment rules above whatever is set there, so there is no need to change it.

## Page copy

About, FAQ, Awards rules, Nominate guidance, the legal pages and Home text are edited in the page editor. See [Block patterns and page copy](/theme/block-patterns).

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
2. In the **Story details** box, choose the **Story type (required to publish)**: interview, feature, spotlight, opinion, announcement or news. The **SEO title** and **SEO description** are in the same box.
3. Tick the profiles the story is about under **Linked profiles (public only)**, and choose its industries. Each linked public profile appears in the "On the record" block, and the profile page lists the story.
4. Optionally pick a **Related event**.
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
| latest | The newest eligible items |
| random | A random selection of eligible items, fixed for the day and cached |
| manual | The items an editor chooses and orders |
| pinned then fill | Manual picks first, remaining slots filled by latest or random (the **Fill** setting) |

1. Open **Directory > Home page settings**.
2. For each block (**On the record**, **Stories on the home page**, **Stories page**) choose a **Mode** and a **Count**. For the two story blocks you can tick **Featured first**.
3. To pin items, use **Find an item to pin**: search, press **Pin**, then **Move up**, **Move down** or **Remove pin** to order them.
4. Press **Preview without saving** to check the block, then **Save**. Saving refreshes the cache so the new picks appear, and the change is audited.

The same screen also holds **Home page hero videos**, the four clips behind the Home page title.

Eligible means public, reviewed and not suppressed. A payment or label never makes an item eligible and never ranks it. Items without a portrait are skipped where a block needs one. The Nominees and winners block is automatic and has no setting here. The Events block is automatic and shows the next three upcoming events.

A sensible starting setup:

| Block | Mode |
|---|---|
| On the record | pinned then fill, Fill random (the default) |
| Stories on the home page | latest, with Featured first ticked |
| Stories page | latest, with Featured first ticked |

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

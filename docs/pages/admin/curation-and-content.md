# Curation and content

How staff decide what the home page and the stories page show, and how stories, comments and page copy are managed. Most of this is native WordPress. The curation settings and the comments are decided in D-059 and are requirements for the build; the prototype does not draw a Home page settings screen.

## Who does what

| Work | Where | Capability | Default roles |
|---|---|---|---|
| Choose how home blocks pick items, pin items | Home page settings (not drawn yet) | `wiw_curate_home` | Editor, Administrator |
| Write, schedule and publish stories | Posts | `edit_posts`, `edit_others_posts`, `publish_posts` | Editor, Administrator |
| Mark a story Featured | Post, sticky flag | `edit_others_posts` | Editor, Administrator |
| Mark a story Sponsored | Post | `wiw_set_sponsored` | Editor, Administrator |
| Create and publish events, link one to a story | Events | `wiw_manage_events` | Editor, Administrator |
| Moderate comments | Comments (wpDiscuz) | `moderate_comments` | Editor, Administrator |
| Edit page copy | Page editor | page-editing capabilities | Editor, Administrator (to confirm) |

Because WordPress capabilities can be assigned to any role, the Administrator can give `wiw_curate_home` to a Reviewer, or take it from an Editor (REQ-244).

## Home page and stories curation

Each home page block that lists profiles or stories has a **mode** (REQ-257). The blocks are On the record, Stories, and Nominees or winners.

| Mode | What it shows |
|---|---|
| Latest | The newest eligible items |
| Random | A random selection of eligible items. The pick is fixed for the day and cached, so the page stays fast and every eligible item gets a turn |
| Manual | The items an editor chooses and orders |
| Pinned then fill | Manual picks first, with the remaining slots filled by Latest or Random |

The stories page uses the same modes. Featured stories (the native sticky flag) come first.

### What is eligible

Only items that are public, reviewed and not suppressed. A payment, an arrangement or a label never makes an item eligible and never ranks it (REQ-183). Where a block needs a portrait, items without one are skipped. The block reads items only through the public read models, so a hidden or unreviewed item cannot appear.

### Recommended starting setup

| Block | Mode |
|---|---|
| On the record | Pinned then fill, filling with Random |
| Stories | Featured first, then Latest |
| Nominees and winners | Automatic from the published awards; there is no choice |

### Saving a change

Saving the screen purges the cache so the new picks appear. Every change is audited.

Today's prototype home page picks On the record by a fixed rule: people with a portrait first, then organisations. No trust label picks them. That rule is the default the settings screen would replace.

## Stories

Stories are native WordPress posts: title, body, author, hero image, tags and SEO fields, with scheduling, revisions and trash (REQ-027). Types are interview, feature, spotlight, opinion, announcement and news. A story can be linked to profiles and industries, and a profile page lists its related stories (REQ-028). The prototype no longer draws the article editor, because WordPress provides it (D-059).

| Flag | How it works |
|---|---|
| Featured | Applies to stories only, through the native sticky flag. No plugin capability is added (REQ-183) |
| Sponsored | Applies to stories only. A visible label with the sponsor's name, set by hand or by a linked arrangement. See [Commercial and sponsors](./commercial-and-sponsors) |

Profiles are never Sponsored or Featured.

The stories page layout is a magazine-style grid with a masthead sign-up. A sign-up is recorded in the Subscribers list and confirmed by email (REQ-249).

## Comments

Stories have comments through the **wpDiscuz** plugin (REQ-258).

1. A reader enters a name, an email and a consent tick. There is no account or login (D-034).
2. The comment is held for moderation. In the prototype it shows "awaiting moderation".
3. A user with `moderate_comments` approves it, and only then does it appear.

Safeguards: a honeypot, rate limits and Akismet or an equivalent against spam. Commenter emails are never shown. Gravatar is switched off. Notification emails to commenters need an opt-in. Comments are not offered on profiles. Comments are personal data: they belong in the PII catalogue, the data-rights export and erase, and the processor register.

Comment moderation uses the WordPress defaults (D-060, Q97): comments wait for approval and the Editor approves or removes them.

## Page copy

About, FAQ, Awards rules, Nominate guidance, legal pages and the home page text are edited by staff in the page editor, not in code (REQ-260). The FAQ is a repeatable question-and-answer block with groups. Brand colours, fonts and the logo stay in the theme tokens file.

Page copy is edited in the native block editor with theme block patterns (D-060, Q98).

Until the build exists, the About copy is draft text for review.

## WordPress native or custom

| Part | Status |
|---|---|
| Stories, Featured, scheduling, revisions | Native |
| Page editing | Native block editor (D-060) |
| Comments | Plugin (wpDiscuz) |
| Curation modes and the Home page settings screen | Custom plugin, proposed |

## Events and the story editor

Events are managed on their own [Events](/pages/admin/events) screen, not in the story editor (REQ-262, D-063). The story editor has one extra field, Related event, which picks one published event to show at the end of the story (REQ-265). A sponsored placement set to Home or Both fills a labelled slot in the stories block and never changes the order of the other items (REQ-263). Events are not chosen by the curation modes; the Home page shows the next three upcoming events automatically.

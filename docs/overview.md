# What WHOISWHO is

WHOISWHO is an African discovery, recognition and media platform. It documents the people, businesses and institutions shaping the continent, tells the stories behind them, and recognises achievement through independent awards. Three words organise everything: **Influence, Achievement and Legacy**.

::: tip Reading order
1. This page: what the platform is and how it is put together.
2. [Features](/features/): what we are building, feature by feature, with acceptance criteria.
3. [Pages](/pages/public/), [Theme](/theme/) and [Plugins](/plugins/): where each feature lives in the build.
4. [Open questions](/open-questions): what still needs an answer.
:::

## What visitors get

There are no public accounts. Everything below works without signing in.

| Area | What a visitor can do |
|---|---|
| Directory | Browse people, businesses, institutions and industries, with filters |
| Profiles | Read facts with a source beside each one; suggest a correction; claim a profile; ask for removal; report a problem |
| Search | One search across profiles and stories |
| Stories | Read interviews, features and news in a magazine-style grid; sign up for the stories by email; comment |
| Events | See what is coming up, filter by city and industry, and follow the link to register on the organiser's site |
| Awards | Read the rules, nominate while nominations are open, see the nominees, see the winners |
| Get listed | Send an enquiry about a listing, sponsored stories, media work or awards sponsorship |
| Trust pages | About, FAQ, contact, complaints and takedown, data rights, legal |

## What staff get

Staff work in the WordPress admin. There are three default roles, and the Administrator can change capabilities per user or create more roles.

| Role | Does |
|---|---|
| Reviewer | Reviews incoming records, resolves duplicates, assigns existing terms, can bulk unpublish |
| Editor | Everything a Reviewer does, plus editing profiles, the support inbox, claims, stories, awards and curation |
| Administrator | Everything, plus the commercial register, import, exports, audit log, users and roles, and the sprint thresholds |

## How it is put together

| Part | What it is | Where it is described |
|---|---|---|
| Theme `whoiswho` | The public front end: templates, design tokens, components, motion | [Theme](/theme/) |
| Plugin `whoiswho-core` | The platform logic: directory, review, import, claims, commercial register, content, awards, compliance, audit | [Plugins](/plugins/) |
| Must-use plugin `whoiswho-guard` | Rules that must always hold, whatever else is installed | [Guard](/plugins/guard) |
| Third-party plugins | Two-factor sign-in, comments, spam protection, role editor, SEO, page editor, cache, backups | [Third-party plugins](/plugins/third-party) |
| WordPress itself | Posts, users, roles, sign-in, taxonomy terms, the media library | [Stack](/build/stack) |
| Acquisition pipeline | A separate system that finds and prepares profile data; it meets the website only through the import contract | [Data from acquisition](/concepts/data-from-acquisition) |

## Rules that do not change

- **Public only after review.** An unreviewed organisation profile carries the label "Compiled automatically from public sources. Human review pending."
- **Money never buys recognition.** Payment never affects nominations, judging, winners, profile ranking, Featured, Claimed or any label.
- **Only stories can be Sponsored.** A sponsor can pay for a story to be placed in a labelled slot for set dates. The label always shows the sponsor's name, and the slot never reorders the other stories or search. Profiles are never Sponsored or Featured.
- **Claimed is a staff mark.** Staff see a checkmark on a claimed profile. The public sees nothing, and the Claim button disappears. There are no Verified profiles.
- **Claimed profiles are edited under review.** A Reviewer or Administrator approves every edit by the assigned user before it is public.
- **Privacy by default.** Personal data is minimised, requests have due dates, and a valid removal request hides the profile within two business days.

The reasoning behind these is in [Concepts](/concepts/).

## Where the work stands

- **Prototype:** done for the public pages (high fidelity) and the staff screens (wireframe). It is the visual reference for the build, see [Prototype guide](/theme/prototype-guide).
- **Specification:** the feature, theme, plugin and page documents in this site.
- **Website build:** not started. The next step is a build plan that turns the features into a GitHub project with issues that agents can pick up.
- **Acquisition:** runs separately and is not described here beyond the contract.

## Repository map

| Path | What it holds |
|---|---|
| `docs/` | This documentation site and the working records it is drawn from |
| `prototype/` | The clickable prototype |
| `acquisition/` | The data acquisition pipeline (separate) |
| `scripts/` | Prototype checks and generators |
| `site/` | The WordPress code, created when the build starts |
| `STATUS.md`, `CLAUDE.md` | Session handover and the rules for the agents working here |

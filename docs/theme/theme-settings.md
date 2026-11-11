# Theme settings

This page lists what the theme (parent `whoiswho`, active child `whoiswho-child`, REQ-273) is configured with, and what lives in settings, in the tokens file, in code or in plugin options. The rule behind all of it: brand values live in one place, and a build check fails if they appear anywhere else (REQ-052, REQ-190).

## Four places a value can live

| Place | Holds | Who changes it |
|---|---|---|
| Theme tokens file (`theme.json` plus a tokens file) | Colours, fonts, spacing, radii, motion timings, logo files | A developer, through a pull request |
| Theme configuration | Brand name, long-form name, edition name, legal trading name, tagline, domain | A developer, through a pull request |
| Plugin options (autoload off) | Wording and behaviour the team changes at runtime, such as the pending-review label and the Nominations switch | An Administrator in wp-admin |
| Page editor content | Copy on editor pages ([Blocks and patterns](/theme/blocks-and-patterns)) | Editors |

The architecture sets brand values apart from options on purpose: they are not in WordPress options.

## Brand configuration

| Setting | Prototype value | Note |
|---|---|---|
| Brand name | WHOISWHO | The logo wordmark is its shortened form (D-060). A single value keeps the name in one place. |
| Long-form name | Who Is Who In Africa | From REQ-052 |
| Legal trading name | whoiswhoafrica | From REQ-052 |
| Tagline | Influence. Achievement. Legacy. | |
| Edition name | WHOISWHO in Africa: The Maiden Edition | Shown on the awards pages and the home page band |
| Domain | wiwi.africa | Used for share URLs |

In the prototype these sit in `prototype/tokens.js` as `W.brand`. In the build they move to the theme configuration.

## Tokens

All design values are defined once, in the tokens file ported from `prototype/theme/tokens.css`. See [Brand and tokens](/theme/brand-and-tokens) for the colours, type and spacing. A CI check fails the build if brand colours or fonts appear outside the tokens file.

| Group | Examples |
|---|---|
| Palette | The seven guide colours and derived tints |
| Semantic | `--c-bg`, `--c-text`, `--c-cta`, `--c-link`, `--c-line`, `--c-focus` |
| Type | Repline for headlines, Montreal for text; sizes and line heights |
| Space and shape | Spacing scale, radii, container widths |
| Motion | Durations and easings used by reveals, header and drawer |

Font licences and the primary brand colour are not confirmed. The values in the file are working choices.

## Logo

The logo files are supplied assets. The header and drawer render the logo through a CSS mask so its colour follows the surrounding text and one file serves light and dark backgrounds. Replacing the logo means replacing the asset in the theme, not editing templates.

## Menus

| Menu | Content | Rule |
|---|---|---|
| Header | Directory, Awards, Stories, About, search icon, "Get listed" | Groups open to sub-menus. The Nominate link is hidden while the Nominations switch is off (REQ-259). |
| Drawer (phones) | The same groups as sections that open and close | Same hiding rule. |
| Footer | Trust and rules, Legal, More, Cookie preferences | Nominate hidden while closed. |

Proposal: menus are WordPress menus for ordering and wording, and the conditional hiding is theme code reading the switch.

## Cookie notice

| Setting | Value |
|---|---|
| When it appears | After the visitor has scrolled a little, or at once on a page too short to scroll (REQ-248) |
| Choices | Decline and Accept |
| Before Accept | Only essential cookies run (REQ-062) |
| After the choice | The Cookie preferences button in the footer reopens it |
| Analytics | Privacy-friendly analytics measure visits and journeys (REQ-078); non-essential scripts load only after Accept |

## Sprint tracking (REQ-272)

| Setting | Name | Default | Who changes it | What it does |
|---|---|---|---|---|
| Sprint tracking | `sprint_tracking` | On | Administrator, audited | Shows or hides the sprint card on the Administrator dashboard and the Sprint thresholds screen |

Turning it off hides the sprint tiles and the Sprint thresholds screen from the Administrator menu. Nothing is deleted: the two thresholds are kept and the setting can be turned back on. It does not affect sponsored story deals, the Sponsored stories screen or revenue records. The prototype shows the switch at the top of the Sprint thresholds screen, with a note that in WordPress it is a theme setting (Theme settings > Sprint tracking).

## Settings the theme reads but does not own

| Setting | Owner | Used for |
|---|---|---|
| Nominations switch | Plugin options, a toggle on the Nominations screen, Administrator only | Nominate page and menus |
| Awards stage and published nominees and winners | Awards screens | Awards pages, home button, nominees block |
| Pending-review label wording | Plugin options | Profile pages, cards and share previews (REQ-216) |
| Home and stories curation modes | Plugin options, `wiw_curate_home` | Profile rail and story track (REQ-257) |
| Sponsors for awards | Sponsor records | The "Supported by" block |

The theme reads these through the plugin. It never writes them.

## What must not be configurable

| Not configurable | Reason |
|---|---|
| Showing a trust label on a profile | Profiles carry none, and Claimed is never public (REQ-150) |
| Showing Sponsored on a profile | Only stories can be Sponsored (REQ-155) |
| Ranking by payment | Nothing in the theme or plugin orders by payment (REQ-183) |
| A cover image on a profile | Profiles show one portrait or logo only (REQ-247) |
| A public sign in | There are no public accounts |

## Prototype versus build

| Prototype | Build |
|---|---|
| `prototype/theme/tokens.css` | The tokens file in the theme |
| `W.brand` in `prototype/tokens.js` | Theme configuration |
| `W.PENDING_LABEL` constant | Plugin option |
| Inline script in `index.html` that sets the header state before first paint | A small inline script in the theme head |
| Fonts and videos supplied from a downloads folder | Licensed files in the theme assets, once licences are confirmed |

## Open points

- Font licences for Repline and Montreal, and licences for the hero videos and stock portraits.
- The primary brand colour.
- Whether the cookie and analytics settings need an Administrator screen or stay in code.

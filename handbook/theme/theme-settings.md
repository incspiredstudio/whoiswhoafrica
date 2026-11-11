# Theme settings

This page lists what the theme is configured with and which kind of value lives where. The rule is that brand values live in one place, and that anything staff may change at runtime is a setting or page content, not code.

## Four places a value can live

| Place | Holds | Who changes it |
|---|---|---|
| Tokens file (`theme.json` plus a tokens file in the child theme) | Colours, fonts, spacing, radii, motion timings, logo files | A developer |
| Theme configuration | Brand name, long-form name, edition name, legal trading name, tagline, domain | A developer |
| Settings and plugin options | Wording and behaviour changed at runtime: the pending-review label, the Nominations switch, Home and Stories curation, Sprint tracking | An Administrator, or an Editor for curation |
| Page editor content | Copy on editor pages | Editors |

## Brand configuration

| Setting | Value |
|---|---|
| Brand name | WHOISWHO |
| Long-form name | Who Is Who In Africa |
| Legal trading name | whoiswhoafrica |
| Tagline | Influence. Achievement. Legacy. |
| Edition name | WHOISWHO in Africa: The Maiden Edition |
| Domain | wiwi.africa |

The brand name sits in one place so it is never retyped.

## Menus

| Menu | Content | Rule |
|---|---|---|
| Header | Directory, Awards, Stories, About, search icon, "Get listed" | Groups open to sub-menus. The Nominate link is hidden while Nominations are closed. |
| Drawer (phones) | The same groups as sections that open and close | Same hiding rule |
| Footer | Trust and rules, Legal, More, Cookie preferences | Nominate hidden while closed |

WordPress menus control order and wording. The theme hides Nominate by reading the Nominations switch.

## Cookie notice

| Setting | Value |
|---|---|
| When it appears | After the visitor has scrolled a little, or at once on a page too short to scroll |
| Choices | Decline and Accept |
| Before Accept | Only essential cookies run |
| After the choice | The Cookie preferences button in the footer reopens it |
| Analytics | Privacy-friendly analytics measure visits. Non-essential scripts load only after Accept. |

## Sprint tracking

| Setting | Default | Who changes it | What it does |
|---|---|---|---|
| Sprint tracking | On | Administrator, audited | Shows or hides the sprint card on the Administrator dashboard and the Sprint thresholds screen |

To change it:

1. Open Theme settings and find Sprint tracking.
2. Switch it off or on, and save.
3. When it is off, the sprint tiles and the Sprint thresholds screen disappear from the Administrator menu. Nothing is deleted: the two thresholds are kept, and the setting can be turned back on.

It does not affect sponsored story deals, the Sponsored stories screen or revenue records.

## Settings the theme reads but does not own

| Setting | Owner | Used for |
|---|---|---|
| Nominations switch | A toggle on the Nominations screen, Administrator only | Nominate page and menus |
| Awards stage and live nominees and winners | Nominations | Awards pages, Home button, nominees block |
| Pending-review label wording | Plugin options | Profile pages, cards and share previews |
| Home and Stories curation modes | Plugin options | The profile rail and story track |
| Sponsors for awards | Sponsor records | The "Supported by" block |

The theme reads these through the plugin. It never writes them.

## What is never configurable

| Not configurable | Reason |
|---|---|
| A trust label on a profile | Profiles carry none, and Claimed is never public |
| Sponsored on a profile | Only stories can be Sponsored |
| Ranking by payment | Nothing orders by payment |
| A cover image on a profile | Profiles show one portrait or logo only |
| A public sign-in | There are no public accounts |

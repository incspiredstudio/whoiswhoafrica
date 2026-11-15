# Theme settings

This page lists what the theme is configured with and which kind of value lives where. The rule is that brand values live in one place, and that anything staff may change at runtime is a setting or page content, not code.

## Four places a value can live

| Place | Holds | Who changes it |
|---|---|---|
| Tokens file (`theme.json` plus a tokens file in the child theme) | Colours, fonts, spacing, radii, motion timings, logo files | A developer |
| Theme configuration | Brand name, long-form name, edition name, legal trading name, tagline, domain | A developer |
| Settings and plugin options | Wording and behaviour changed at runtime: the pending-review label, the Nominations switch, Home and Stories curation, Sprint tracking, the image credit and key facts settings for profile pages | An Administrator, or an Editor for curation |
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

## Profile pages

Two settings on the Theme settings screen decide what a public profile page shows. Only an Administrator changes them, and every change is written to the audit trail.

| Setting | Choices | Default |
|---|---|---|
| Image credit on profile pages | **Compact**: a small "i" button on the photo opens the credit ("Photo: A. Mensah · CC BY-SA 4.0"), and the same credit is listed under "Image credits" at the foot of the page. **Full**: the credit line sits under the profile header. **Hidden when not required**: no credit for images that need none (CC0, public domain, or a photo supplied by the person or organisation); CC BY and CC BY-SA images keep the compact credit. | Compact |
| Key facts and sources on profile pages | **Hidden**: the table of facts with their sources is not shown. **Collapsed**: a closed "Key facts and sources" section at the foot of the page that visitors can open. **Shown**: the table in the page body. | Hidden |

To change them:

1. Open Theme settings and scroll to Profile pages.
2. Pick a choice for each setting and press Save profile page settings.
3. Open a profile to check. The change shows at once on every profile.

The licence is never lost: in compact mode the credit is still on the page as text, so the attribution that CC BY and CC BY-SA ask for stays visible. If a credit was typed with the licence at the end ("A. Mensah, CC BY 4.0"), the page shows the licence once.

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
| A header sign-in or public sign-up | The only sign-in link is "Profile owner sign in" in the footer, and staff create every owner account |

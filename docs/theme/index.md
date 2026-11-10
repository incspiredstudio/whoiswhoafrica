# Design overview

This section explains how the WHOISWHO site is meant to look and move, and where each piece lives in the prototype. Read it before changing a page, a colour or an animation.

## The design bar

The public pages aim for Awwwards "Site of the Day" quality. That means confident type, a strict brand palette, real motion that never blocks reading, and layouts that work at 360 pixels as well as at 1600. The identity comes from the brand guide (`docs/brand/WIWI Brand Identity 2.pdf`) and the venture brief.

The staff screens do not share that bar. They keep the plain wireframe look, because the real admin will be WordPress and only the behaviour is being designed.

## What is where

| Area | Look | Source files |
|---|---|---|
| Public pages (home, directory, profiles, stories, awards, forms, legal) | High fidelity, brand theme | `prototype/theme/` |
| Staff screens (review, profiles, claims, register, awards, audit) | Wireframe | `prototype/styles.css`, `prototype/tools.css` |
| Prototype tools (role switcher, Flows menu, Design notes, Design review) | Wireframe | `prototype/core.js`, `prototype/flows.js` |

The two looks share one page. Both stylesheet sets stay loaded and switch through the `media` attribute, so nothing flashes when you move between a public and a staff page. See [the prototype guide](/theme/prototype-guide).

## The principles

| Principle | What it means in practice |
|---|---|
| One source for brand values | Colours, fonts, spacing and motion timings live in `theme/tokens.css`. No page hard-codes them. See [Brand and tokens](/theme/brand-and-tokens). |
| The page works without motion | Every animation is added on top of finished markup. Reduced motion, data saver or a missing script leaves a complete page. See [Motion and performance](/theme/motion-and-performance). |
| Colour is never the only signal | Labels differ in shape as well as colour, and text meets contrast checks. See [Responsive and accessibility](/theme/responsive-and-accessibility). |
| Mobile first | Styles start at phone width and add rules at larger widths. |
| Money never shapes the look | The Sponsored label is the only paid disclosure, and only stories carry it. A sponsored slot looks like any story card plus the label (D-055, D-062). Profiles show no trust label to the public (D-054). |

## Page inventory

Every public page and state has an entry in the wireframe inventory. The in-prototype page `#/design-review` lists them all with links, grouped as Home and about, Directory, Profile types, Profile states and labels, Awards, Stories, Forms, Legal and system, and Single-use link pages. Use it to open each page at 360, 768 and 1280 pixels.

## What is decided and what is open

| Topic | Status |
|---|---|
| Palette: seven colours from the guide | Decided |
| Typefaces: Repline for headlines, Montreal for text | Decided in the guide; font licences not confirmed |
| Primary brand colour | Not confirmed. Orange is used for calls to action and red for links and accents, as a working choice |
| Public name on the logo | The name is WHOISWHO; the logo wordmark is its shortened form (D-060) |
| Photos, hero videos | Placeholders and supplied files; licences not confirmed |
| Home hero | Four autoplaying vertical videos replaced the earlier 3D logo |

`docs/design-inspiration.md` holds notes on two award sites used as references. Every point in it is marked unverified, so treat it as a list of things to check, not findings.

## Pages in this section

The first group describes the design. The second is the build specification for the `whoiswho` theme: what to build, page by page.

### Design

| Page | Covers |
|---|---|
| [Brand and tokens](/theme/brand-and-tokens) | Colours, type, logo files, marks, spacing |
| [Components](/theme/components) | Poster, cards, header, forms, buttons, labels |
| [Motion and performance](/theme/motion-and-performance) | Smooth scroll, hero, reveals, confetti, reduced motion |
| [Responsive and accessibility](/theme/responsive-and-accessibility) | Breakpoints, mobile decisions, audits |
| [Prototype guide](/theme/prototype-guide) | How the prototype works and how to add a page |

### Build specification

| Page | Covers |
|---|---|
| [Templates](/theme/templates) | Every public template: address, data it reads, states it must handle |
| [Blocks and patterns](/theme/blocks-and-patterns) | What staff edit in the page editor and what stays in code |
| [Theme settings](/theme/theme-settings) | Brand configuration, tokens, menus, cookie notice and what lives where |
| [SEO and sharing](/theme/seo-and-sharing) | Titles, canonical, share cards, structured data, sitemap and noindex rules |
| [Theme acceptance](/theme/theme-acceptance) | The testable checklist a page must pass to count as done |

# Design inspiration

For the **later visual design phase**. Not used for the wireframe, which stays structural and keeps its current look. Linked from `docs/GUIDE.md`.

**Rules.** These are references only. Do not copy layouts, imagery, code, text or branding. WHOISWHO needs its own identity, built on **Influence. Achievement. Legacy.**, with the 3D hero and animations planned for the build phase.

**How reliable these notes are.** Every point is marked:
- **seen**: someone looked at the live site in a browser.
- **unverified**: taken from page text only.

No browser tool was available on 2026-11-08. The two sites were read as converted text, which carries no CSS, no script names and no rendered pixels. **So every point below is unverified.** Fonts, colours, motion and layout are listed as things to check, not as findings. A person (or an agent with a browser) must open both sites before any point is promoted to "seen".

---

## 1. Premia Runeta (https://premiaruneta.ru/)

The Russian national internet award, 23rd edition (2026). Pages read: home and participation.

| Topic | What to learn from it | Status |
|---|---|---|
| Hero treatment | The first screen leads with the award name, edition number ("XXIII"), year and place, then a theme for the ceremony, then key facts (venue, application deadline, ceremony date) and a link down to the nominations. Text suggests two large static images (a trophy and a globe). Learn: edition and date up front; a theme gives the year an identity. | unverified |
| Typography | Not known. Check the display face, how the Roman-numeral edition mark is set, and any Cyrillic and Latin pairing. | unverified |
| Colour approach | Not known. The "theme of the year" idea (a century's aesthetic) suggests colour changes per edition. Check. | unverified |
| Navigation | One long home page with anchors (Nominations, Voting) plus separate pages for participation, news, photobank and media. A single "Submit application" call to action sits in the header. Learn: one primary action, and a photobank and press page for credibility. | unverified |
| Categories | Seven numbered sections holding 18 numbered nominations. Each has a number, a title, a tagline and a description, with a "+" marker that suggests expanding. Special cases are handled in the list itself (bundled sub-nominations, a placeholder for a partner-named nomination, a people's vote). Learn: numbering makes a long list scannable. | unverified |
| Nominees and winners | Not on the pages read. Winners appear as news and in a link to the previous year's archive on a separate subdomain. Learn: keep each year's winners as a stable archive. Check how the archive presents nominees and winners. | unverified |
| Interactions | Anchor scrolling, expandable nominations, and an application form that verifies the email with a code before submit. Learn: a short verified-email step before a long form. | unverified |
| Motion | Not known. Check scroll effects, hover states and any transitions. | unverified |
| Partners | A block of organisers and tiered partner logos, with a media-coverage block. Learn: credibility through who stands behind the award. Keep our rule that sponsorship is always labelled and never implies verification. | unverified |

## 2. The Webby Awards (https://www.webbyawards.com/)

The long-running international award for the internet. Pages read: home and the categories page.

| Topic | What to learn from it | Status |
|---|---|---|
| Hero treatment | A call-for-entries banner on top, then an animated image (a GIF), the edition name, the entry deadline and an "Enter now" button. Learn: the current call to action and deadline are the first thing seen. Check whether the live hero is video or canvas rather than a GIF. | unverified |
| Typography | Not known. Check type scale and how large display headings are used. | unverified |
| Colour approach | Not known. | unverified |
| Navigation | A short main nav (About, Categories, Winners, Judges) plus a secondary row (Features, Events, Reports, Sponsors, Press). Search and a gallery login sit apart. Categories has its own sub-navigation (eligibility, judging process, judging criteria, awarding, FAQ). Learn: awards rules and judging process are pages of their own, which supports trust. (We have no public login, D-034.) | unverified |
| Categories | Nine category cards on the home page, each an image and a link. The categories page is one long page with a filter list at the top and, per category, a description and subcategories. Learn: card entry point, then a detailed rules page. | unverified |
| Nominees and winners | A "Browse the winners" block of image cards linking into a separate winners site by category, with year and sort options in the links. Special award winners get their own cards with a label. Winner quotes appear as cards with name, award and photo. Learn: winners as a browsable gallery with year and category filters, and a distinct treatment for special awards. Check the winners site, which was not read. | unverified |
| Interactions | Overlays for search, follow and a newsletter, a draggable slider hint, and a sticky search (inferred from asset names). | unverified |
| Motion | Animated GIFs on cards. The real motion behaviour (scroll effects, transitions) is not known. | unverified |
| Credibility devices | Judges, press quotes and a sponsors strip. Learn: show the people who judge and the press that cites the award, as long as sponsors never imply a win. | unverified |

---

## 3. What this means for WHOISWHO (still unverified, to test in the design phase)

- **Edition identity.** A named edition ("The Maiden Edition") with a year theme could carry the brand.
- **A clear first action.** Directory search, with the nomination call to action only while the nominations flag is on (REQ-194).
- **Numbered or carded categories** for awards, and an archive of winners by year and category.
- **Profile and winner cards** that show the trust label (Unclaimed, Claimed, Verified, Featured) and the Sponsored disclosure, never mixed (docs/GUIDE.md section 4).
- **Rules pages** (eligibility, criteria, process) treated as part of the brand's credibility.
- **No accounts** (D-034): none of the login or gallery patterns apply.

## 4. Open design questions

Each needs an answer before the design phase starts. None blocks the wireframe review or the WordPress architecture.

| # | Question | Notes |
|---|---|---|
| DQ1 | **Brand assets.** Is there a logo, wordmark, colour palette and typefaces? | The brief cover and the WIWI/WHOISWHO name question (docs/change-review-2026-11-07.md, Q40) are unresolved. |
| DQ2 | **3D concept.** What is the 3D hero? Object, scene or abstract form? Interactive or a loop? | Must express Influence. Achievement. Legacy. Needs a fallback for no-WebGL and reduced motion. |
| DQ3 | **Motion budget.** How much animation, and what is the load limit on a mid-range phone on a slow connection? | Affects hosting and cPanel limits and accessibility (reduced motion). Propose a page-weight and frame-rate budget. |
| DQ4 | **Dark or light.** Dark, light, or both? | A dark hero suits 3D; long reading pages and profiles may need light. Contrast must meet WCAG 2.1 AA. |
| DQ5 | **Profile card style.** How does a profile card show photo, name, role, location and trust label? | Stubs have no photograph unless supplied or licensed (REQ-162), so the card must work without a photo. |
| DQ6 | **Winner card style.** How do winners differ from nominees and from profile cards? | Awards must not borrow trust styling that could be read as Verified. |
| DQ7 | **Imagery and photography.** Commissioned, licensed, illustrated or generated? | Licence rules in D-024. |
| DQ8 | **Language and tone.** English only for now (D-025). Confident but neutral, matching REQ-167? | Affects copy on hero and About. |

## 5. Process at the design phase

1. Before any theme is built, propose **two or three distinct visual directions**. Each gets a **homepage concept** (hero, first scroll, directory entry, awards entry) for you to choose from.
2. Open both reference sites in a browser first, and promote or correct the points above.
3. The chosen direction becomes the WordPress theme's design tokens. `prototype/tokens.js` changes only if the wireframe is reused.

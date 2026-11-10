# Theme acceptance

This page is the checklist a theme page must pass before it counts as done. Each item is testable. Items marked "required" come from the requirements; the rest are working standards taken from the prototype, to be confirmed when the theme shell is built.

A page is done only when every item in "Every page" passes and the item for that page in "Per page checks" passes.

## How it is tested

| Tool | Covers |
|---|---|
| Playwright end-to-end tests (`npx playwright test`) | Per-page checks, states, forms, keyboard use, widths |
| axe-core, run inside the end-to-end tests | WCAG 2.1 AA rules |
| Visibility matrix test | One profile per state on every surface (see [SEO and sharing](/theme/seo-and-sharing)) |
| Theme-leak check in CI | Fails if brand colours or fonts appear outside the tokens file (REQ-052, REQ-190) |
| Manual pass on real devices | Touch, motion feel, video playback |

A page is checked at 360, 768, 1280 and 1600 pixels.

## Every page

### Accessibility (required: WCAG 2.1 AA, REQ-043)

- [ ] axe-core reports no violations at any tested width.
- [ ] Text contrast meets AA for every colour pair, including text over video and over the colour blocks.
- [ ] Colour is never the only signal. Labels differ in shape as well as colour.
- [ ] A skip link reaches `main`. Header, main and footer are landmarks. Headings are in order, one `h1`.
- [ ] Every control is reachable and usable by keyboard, with a visible focus ring. The drawer and dialogs trap Tab and return focus on close.
- [ ] Every image has alt text. Decorative marks have empty alt text and are hidden from assistive technology.
- [ ] Every form field has a label and its hint is attached to it. The bot check is a labelled control.
- [ ] Page language is set. Confirmation and error messages are announced.
- [ ] Zoom to 200 percent loses no content or function.

### Responsive (required: usable at 360 px, REQ-040)

- [ ] No horizontal scroll at 360 pixels.
- [ ] Layout is built mobile first and changes at 640, 768 and 1024 pixels as the page needs.
- [ ] Hover-only behaviour has a touch equivalent or is not needed to use the page.
- [ ] Touch targets are large enough to tap without error.
- [ ] Browsers: the latest two versions of Chrome, Safari, Edge, Firefox and Android Chrome (REQ-055).

### Motion

- [ ] With reduced motion on, there are no reveals, no smooth scroll, no autoscroll and no confetti, and the page is complete.
- [ ] With data saver or a very slow connection, the home page shows its static version.
- [ ] With scripts disabled, every page is readable and every link works.
- [ ] Animations never block reading or input.

### Header and chrome

- [ ] No flash of a wrong header on load or refresh, on desktop and on phones. An inline script sets the header state before first paint.
- [ ] The header is transparent over the home hero only, and solid elsewhere. Hover or focus never turns it grey or white by mistake.
- [ ] Drawer sections animate open and closed without snapping.
- [ ] The Nominate link is absent from the header, drawer and footer while the Nominations switch is off (REQ-259).
- [ ] The cookie notice appears after a little scroll with Decline and Accept. No non-essential script runs before Accept (REQ-062, REQ-248).

### Content rules

- [ ] No page shows a trust label on a profile, a Claimed mark, or Sponsored on a profile (REQ-150, REQ-155).
- [ ] No page shows a profile cover or banner image (REQ-247).
- [ ] No brand value is hard-coded outside the tokens file or theme configuration.
- [ ] Nothing offers sign in or sign up.

### Performance

- [ ] Profile and directory pages reach Largest Contentful Paint of 2.5 seconds or better on a mid-range Android phone over throttled 4G (required, REQ-041, an assumed target).
- [ ] Images are resized and in modern formats (REQ-042).
- [ ] Fonts are loaded in a way that shows text at once.
- [ ] The theme adds JavaScript only where a page needs it. Video loads without delaying the first view.
- [ ] A numeric budget for page weight and script size is not written down yet. Set one from a measurement of the finished shell and add it here.

## Per page checks

| Page | Must pass |
|---|---|
| Home | Four hero videos autoplay muted and loop. Hover widens one video with more contrast and saturation and no zoom. Phones show a 2 by 2 grid. Scroll exit works. The nominees block is absent before publication. The awards marquee shows category names and the page has no awards band or button. The Events block shows the next three upcoming events and is absent when there are none. |
| Directory lists | Filters are in the address and removable as chips. The count is announced. Sixteen per page. Only reviewed profiles are listed. Phone filters collapse. |
| Profile | All states in [Templates](/theme/templates) render correctly. Every fact shows a source and retrieval date. The Claim button is absent on a claimed profile. Contact is never shown directly. The "When shared" card matches the share tags. |
| Search | One search returns profiles then stories with a count. Nothing non-public appears. No-results state is shown. |
| Stories list | No hero. Mosaic, grid, sign-up block and filters work, with no Events filter or strip. Images are 16:9. Sponsored label on sponsored cards. A story with a linked event shows the Related event card at its end. |
| Events | The list shows upcoming events first, then past, and filters by city and industry. The Event page shows details and a register link, or "Past event" with no button. Header and footer each link to Events. |
| Story | Sponsored notice where it applies. Linked profiles show only if public. Comments form needs name, email and consent, and posts as held for moderation. |
| Awards overview | The timeline and button follow the stage. Categories link to their pages. |
| Nominees and winners | Before publication the "not published" message shows. Winners filters live in the address. Confetti plays once and not at all under reduced motion. |
| Nominate | Switch off: "Nominations are closed", no form, no sign-up, guidance present. Switch on: all six steps work, evidence with contact details is blocked, duplicate warning appears, and a rate limit message appears after too many. |
| Claim, correct, remove, report | Every field is labelled. The bot check and privacy notice are present. A done page follows with a reference. Expired links show the expired page. |
| Get listed and contact | No prices or packages appear (REQ-120, REQ-177). Forms submit and show a done page. |
| Editor pages (About, FAQ, legal) | Blocks edit and render. The FAQ's first question is open. Draft notes appear and can be removed. |
| 404, 410, 500 | Correct status codes. 410 carries noindex and no reason. 500 shows no technical detail. |

## Definition of done for a template

1. It builds against the read-model fixtures and handles every state listed for it in [Templates](/theme/templates).
2. Every "Every page" item passes at the four widths.
3. Its per-page check passes.
4. Its metadata matches [SEO and sharing](/theme/seo-and-sharing).
5. The end-to-end and axe tests for it are in the repository and pass in CI.

## Open points

- A numeric performance budget beyond the Largest Contentful Paint target.
- The final list of tested widths and devices.
- Which parts of the prototype's audit script move into the Playwright suite. The prototype's audit script is not kept in the repository.

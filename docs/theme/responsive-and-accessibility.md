# Responsive and accessibility

This page records how the public pages adapt to screen size, the mobile decisions made during the design review, and how accessibility is checked.

## Breakpoints

The CSS is mobile first: base rules apply at phone width and `min-width` queries add layout. The prototype is reviewed at 360, 768, 1280 and 1600 pixels.

| Width | Used for |
|---|---|
| 360 | Small phone, the narrowest check |
| 640 (40rem) | Larger phones and small tablets |
| 768 (48rem) | Tablet; the home hero switches from a 2 by 2 grid to a row of four |
| 1024 (64rem) | Desktop layouts, the full mega menu, the wide stories track |
| 1280 and 1600 | Wide desktop |

`site.css` and `home.css` use about 65 `min-width` queries and 17 `max-width` queries, plus `(hover: none)` rules for touch and `prefers-reduced-motion` rules.

## Mobile decisions

These were asked for or confirmed in review and are part of the design now.

| Page | Decision |
|---|---|
| Home hero | The four videos sit in a 2 by 2 grid on phones |
| Home "On the record" | Auto-advances on phones; no arrows and no pause button |
| Home nominees | Redesigned for phones: heading, paragraph, call to action and the grid; no filter buttons on mobile |
| Home and stories | Story images are 16:9 banners; on phones and tablets tiles stack with the picture first and text below |
| Stories | No hero; a post grid starts the page |
| Directory | Filters collapse into a drop-down panel over the results |
| Header | Translucent with blur; drawer sections animate; no flash on refresh |
| Profiles | Photos are larger rounded squares, not small circles |
| Spacing | Nested tight sections no longer pad twice; gaps between sections are smaller on phones |

## Touch rules

- Hover effects have a touch equivalent or are not needed to use the page. Rules inside `@media (hover: none)` replace hover-only behaviour.
- The home header scrim and the hero text stay readable without a pointer.
- Smooth wheel scrolling is off on touch; the page scrolls natively.
- The "On the record" rail pauses after a touch so it does not move under a finger.

## Accessibility

| Area | Rule |
|---|---|
| Contrast | Text meets WCAG AA. Example: the stories newsletter block uses `--blue-700` because white on the lighter blue failed at 4.36 |
| Colour | Labels differ in shape as well as colour |
| Keyboard | Focus rings use `--c-focus` (and orange on dark); dialogs and the drawer trap Tab |
| Motion | Reduced motion removes reveals, smooth scroll, autoscroll and confetti |
| Images | Every image has an alt text; decorative marks use empty alt and `aria-hidden` |
| Forms | Labels on every field and hints beside them; the human check is a labelled checkbox |
| Language | `lang="en"`; the page announces toasts through `role="status"` |
| Structure | A skip link to `main`, and header, main and footer landmarks |

The requirement is REQ-043: it applies to every screen and is tested, not a separate page.

## How it is checked

| Check | What it does | Where |
|---|---|---|
| Smoke test | Renders every route for every role and asserts rules such as "visitors never see trust labels" | `scripts/smoke-prototype.js` |
| Browser audit | Loads each public route at 360, 768, 1280 and 1600 pixels, measures horizontal overflow, runs axe-core and collects console errors, optionally with screenshots | An ad hoc Puppeteer script, not kept in the repository |
| Manual pass | Open `#/design-review` and walk the list at three widths | In the prototype |

The last full audit covered 72 routes at 360, 390, 768 and 1280 pixels with no overflow, script errors or axe failures. The audit script is not in the repository, so re-run it from a local copy or recreate it before relying on the numbers.

## Checklist before a public page is done

1. No horizontal scroll at 360 pixels.
2. Every interactive element reachable by keyboard, with a visible focus ring.
3. Contrast checked on every new colour pair.
4. Reduced motion shows a complete page.
5. Touch behaviour tested on a real phone (see [Run the prototype](/getting-started/run-the-prototype)).
6. Smoke test passes.

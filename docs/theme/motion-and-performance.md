# Motion and performance

This page explains every animation on the public site, how each one switches off, and what keeps the pages fast. The rule is simple: motion is added on top of a finished page, and nothing is needed to read it.

## Where the code is

| File | Holds |
|---|---|
| `theme/motion.js` | Reveals, smooth scroll, page transitions, mega nav, drawer, rail, confetti |
| `theme/home.js` | Home page choreography: hero, colour scenes, cursor, preview card |
| `theme/home.css` | Hero videos and the scroll exit |
| `theme/vendor/lenis.min.js` | The smooth-scroll library |
| `index.html` | A small inline script that sets the header state before first paint |

## Switches that turn motion off

| Condition | Effect |
|---|---|
| `prefers-reduced-motion: reduce` | Reveals show at once, smooth scroll and the rail's autoscroll stop, confetti does not run, video hover transitions are removed |
| Data saver or a 2G connection | The home page skips its choreography and shows the static page |
| No `IntersectionObserver` | Everything that would reveal is simply visible |
| Touch or no fine pointer | Smooth wheel scrolling stays off; touch and keyboard scrolling stay native |

## Smooth scroll

Every public page uses Lenis for wheel scrolling (`smSync` in `motion.js`). The home page runs its own copy while its scenes are live, and the global one yields to it. Areas that must scroll natively opt out: dialogs, the Flows panel, the drawer, the mega menu, filters, textareas, selects and anything marked `data-lenis-prevent`. A page change scrolls to the top immediately.

## Reveals

Elements marked `data-reveal` fade and rise when they enter the viewport, using one `IntersectionObserver`. A timer of 1.2 seconds is a safety net: anything already on screen that has not revealed is shown anyway.

## Home hero

Four vertical videos sit side by side and autoplay muted in a loop (`theme/video/hero-1` to `hero-4`, each with a poster image). They replaced the earlier 3D logo.

| Behaviour | Detail |
|---|---|
| Hover | The video under the pointer grows wider, the others shrink; the hovered video gets more saturation and contrast and loses its dimming. There is no zoom. |
| Scroll exit | As the visitor scrolls out of the hero, the frame shrinks inside the page so background shows left, right and below, then the next section takes over. `home.js` sets the CSS variable `--hx` from scroll progress; `home.css` reads it. |
| Phones | The videos form a 2 by 2 grid below 48rem |
| Header | Transparent over the hero, with a scrim for legibility that hides while the header is hovered |

The older 3D particle code (`stage3d.js`, `hero-w.js`) is still in the folder and still loaded, but the home markup no longer carries a canvas for it.

## Other motion

| Piece | Behaviour |
|---|---|
| "On the record" rail | On phones and tablets it advances every 3.2 seconds, pauses for 7 seconds after a touch, wheel or focus, and loops. It has no arrows or pause button. On wide screens the cards are a list with a hover preview |
| Preview card | Shows the name under the pointer, with a photo when the profile has one, else a coloured card with the mark |
| Confetti | One short burst in the brand colours when `#/awards/winners` opens |
| Page transitions | View Transitions where supported; otherwise a page-enter fade |
| Drawer sections | Height and fade animation through the Web Animations API |
| Cookie card | Appears only after a little scrolling, or at once on a short page |

## No flash on refresh

On the home page the header starts transparent. Without care, a refresh draws a solid header and then fades it. An inline script at the top of `index.html` adds `is-home`, `hdr-init` and `at-top` before anything is painted. The `hdr-init` class holds back all header transitions, and the script removes it after the page has loaded (with a six-second timeout as a fallback).

## Performance habits

| Habit | Where |
|---|---|
| Fonts preloaded and self-hosted with `font-display: swap` | `index.html`, `tokens.css` |
| Images lazy-loaded with decoding hints | `home-view.js` |
| Story images sized 1280 by 720 | Cards and the home story track |
| Animations read scroll in one place per page | `home.js` measures once and recomputes on resize |
| Stylesheets switched by `media`, not reloaded | `core.js` |

The requirement targets for page speed are REQ-041 and REQ-042. They are measured in QA, not in the prototype.

## Changing motion

1. Keep it optional: add a class on `html` only when the effect is live.
2. Add a `prefers-reduced-motion` rule for any new transition.
3. Check phone and desktop; touch devices have no hover.
4. Run the smoke test, then open the page in a browser.

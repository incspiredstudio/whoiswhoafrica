# Brand and tokens

This page lists the brand values the public site uses and where they are defined. All of them live in `prototype/theme/tokens.css`, which is meant to be ported to the WordPress theme unchanged.

## Colour

The brand guide gives seven colours. The tokens file marks them with a `guide` comment. Everything else is a tint or shade derived from them, because the guide gives no tints and some text needed more contrast.

| Token | Value | Role |
|---|---|---|
| `--charcoal-900` | #2f2f2f | Text, dark sections |
| `--offwhite` | #f3efe6 | Page background |
| `--orange` | #ffa600 | Calls to action, highlights |
| `--blue` | #0e71fe | Influence block; links use the darker `--blue-700` |
| `--red` | #af0102 | Accent, hover on titles |
| `--green` | #276538 | Colour block, positive states |
| `--purple` | #8036e2 | Legacy block, Sponsored label |

The home page names three colour blocks after the brand words: Influence (blue), Achievement (orange) and Legacy (purple). The four "doors" to the directory use blue, orange, green and purple.

Semantic tokens sit on top of the raw palette (`--c-bg`, `--c-text`, `--c-cta`, `--c-link`, `--c-line` and others). Pages use the semantic names, so a palette change is made in one place.

The primary brand colour is not confirmed. Orange is used for calls to action and red for accents as a working choice.

## Typography

| Use | Typeface | Weights loaded |
|---|---|---|
| Headlines | Repline | 400 |
| Everything else | Montreal | 300, 400, 500, 700 |

Both are self-hosted as `woff2` in `theme/fonts/` with `font-display: swap`. No italics are loaded because the design does not use them. The licences for both fonts are not confirmed; Repline came from a local download. Resolve this before launch.

Sizes are fluid. `--t-xs` to `--t-md` are fixed; `--t-lg` to `--t-4xl` use `clamp()` so headings scale with the viewport. Line heights are `--lh-tight`, `--lh-snug` and `--lh-body`.

## Spacing, shape and motion

| Group | Tokens |
|---|---|
| Spacing | `--s-1` (4px) to `--s-9`, a 4px-based scale; `--gutter` is fluid |
| Layout widths | `--w-page` 82rem, `--w-text` 42rem, `--head-h` 4.25rem |
| Radius | `--r-1` 3px, `--r-2` 8px, `--r-3` 14px, `--r-pill` |
| Shadow | `--sh-1`, `--sh-2`, `--sh-3` |
| Motion | `--m-fast` 140ms, `--m-base` 240ms, `--m-slow` 620ms; easings `--ease-out`, `--ease-inout` |

## Label colours

Labels have their own tokens (`--l-verified`, `--l-featured`, `--l-reviewed`, `--l-pending`, `--l-sponsored`). Their shapes differ too, so colour is never the only signal. Public pages show only the review-state labels and, on stories, Sponsored. Staff-side marks are not public (D-054, D-059).

## Logo and icon

The supplied files are in `theme/assets/` (D-058): `wiw-logo.png`, `wiw-icon.png`, `wiw-icon-black.png`, and the mark files described below.

The header wordmark and the icon are drawn through CSS masks (`.wiw-logo` and `.wiw-w`). The mask images are embedded as data URIs in `site.css`. That matters: a mask loaded from a file URL is blocked on `file://`, so the logo would vanish when the prototype is opened by double-click.

The logo wordmark is the shortened form of the public name WHOISWHO (D-060). Orange is the primary colour.

## Brand marks

Four African (Adinkra-style) symbols replaced the earlier brush strokes. They are `webp` files in `theme/assets/`: `mark-star`, `mark-comb`, `mark-link` and `mark-spiral`. They appear on the home colour blocks, the directory doors and the poster component (see [Components](/theme/components)). Each has a CSS variable (`--m`) so a poster can tint and place one.

### Approved symbols (owner rule, 2026-11-14, issue 808)

Only four symbols may appear anywhere on the site, alone or in a pattern:

| Symbol | Meaning | File (`whoiswho-child/assets/brand/`) |
|---|---|---|
| Adinkrahene (concentric circles) | Greatness, leadership | `mark-star.webp` |
| Dwennimmen (ram's horns) | Humility with strength | `mark-comb.webp` |
| Nkonsonkonson (chain links) | Unity, human relations | `mark-link.webp` |
| Sankofa (heart form) | Learning from the past | `mark-spiral.webp` |

- **Not approved:** the brush-glyph pattern tile (`pattern-tile.webp`) and the brush strokes on the brand guide's Pattern System page. They were removed from the theme. Do not add other symbols, icons drawn as symbols, or new mark files. The interface icons (search, arrow, chevron and so on in `inc/components.php`) and the logo are not symbols in this sense.
- **The pattern** is `pattern-marks.webp`, built only from the four marks by `site/build/pattern-tile.py` (run `python site/build/pattern-tile.py` from the repository root; it needs Pillow). It follows the guide's pattern rule: the marks in varying shades of one colour, in a clean, balanced brick grid where every mark and every shade appears once per row and column.
- The tile is alpha only. Use it as a CSS mask and paint the colour through a token (`background: currentcolor` or a colour token), never as a coloured background image. The token is `--img-pattern-tile`. Keep it subtle: about 5% on the dark footer and 8% on the empty story picture and a 16% dark wash inside posters (more fails text contrast on the orange poster).
- A test (`ChildBrandTokensTest`) fails if the brand folder holds any other file or a theme file points at an unapproved brand image.

## Where the tokens file lives (REQ-273)

The parent theme defines the token structure and sensible defaults. The brand values themselves, meaning the colours, fonts and logo listed on this page, live in the tokens file of the child theme `whoiswho-child`, which is the active theme and overrides the parent. Never edit brand values in the parent.

## How tokens are used

| Do | Do not |
|---|---|
| Read `var(--c-text)` in a rule | Write `#2f2f2f` in a rule |
| Add a new value to `tokens.css` first | Add a one-off colour to `site.css` |
| Use `--s-*` for gaps and padding | Use arbitrary pixel values |

The brand name and tagline sit in `prototype/tokens.js`, the wireframe's own token file. In the WordPress build the rule in `CLAUDE.md` applies: no brand values outside the theme tokens file, which lives in the child theme `whoiswho-child`.

## Related

- Decisions: D-052 (brand name and brief v2), D-058 (logo files).
- Component use of these tokens: [Components](/theme/components).

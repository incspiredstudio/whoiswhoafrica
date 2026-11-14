<!-- verify-after-round-9 -->

# Brand tokens

Brand tokens are the named values for colour, type, spacing, shape and motion that every page uses. They are defined once, in the child theme's tokens file, and no page writes a brand value directly. This page lists them and says how to use them.

## Colour

Seven brand colours come from the brand guide. Everything else is a tint or shade of these.

| Token | Value | Role |
|---|---|---|
| `--charcoal-900` | #2f2f2f | Text, dark sections |
| `--offwhite` | #f3efe6 | Page background |
| `--orange` | #ffa600 | Calls to action, highlights |
| `--blue` | #0e71fe | Influence block. Links use the darker `--blue-700`. |
| `--red` | #af0102 | Accent, title hover |
| `--green` | #276538 | Colour block, positive states |
| `--purple` | #8036e2 | Legacy block, Sponsored label |

The Home page names three colour blocks after the brand words: Influence (blue), Achievement (orange) and Legacy (purple). The four directory doors use blue, orange, green and purple.

Pages use semantic names such as `--c-bg`, `--c-text`, `--c-cta`, `--c-link`, `--c-line` and `--c-focus`, so a palette change is made in one place.

## Typography

| Use | Typeface | Weights |
|---|---|---|
| Headlines | Repline | 400 |
| Everything else | Montreal | 300, 400, 500, 700 |

Both are self-hosted as `woff2` with `font-display: swap`. No italics are loaded. Sizes are fluid, so headings scale with the screen.

## Spacing, shape and motion

| Group | Tokens |
|---|---|
| Spacing | `--s-1` (4px) upward, a 4px-based scale. `--gutter` is fluid. |
| Layout widths | `--w-page` 82rem, `--w-text` 42rem, `--head-h` 4.25rem |
| Radius | `--r-1` 3px, `--r-2` 8px, `--r-3` 14px, `--r-pill` |
| Shadow | `--sh-1`, `--sh-2`, `--sh-3` |
| Motion | `--m-fast` 140ms, `--m-base` 240ms, `--m-slow` 620ms, with `--ease-out` and `--ease-inout` |

## Label tokens

Labels have their own colour tokens and also differ in shape, so colour is never the only signal. Public pages show only the review-state label and, on stories, Sponsored. Staff-side marks are never public.

## Logo and brand marks

- The header wordmark and the icon are drawn through CSS masks, so one file serves light and dark backgrounds. Replace the logo by replacing the asset in the child theme, not by editing templates.
- Four Adinkra symbols appear on the Home colour blocks, the directory doors, posters and the background pattern. They are the only approved symbols; see [Approved symbols](#approved-symbols).
- The public name is WHOISWHO. The logo wordmark is its shortened form.

## Approved symbols

WHOISWHO uses exactly four Adinkra symbols, and no others:

| Symbol | Meaning in brief |
|---|---|
| **Adinkrahene** | "Chief of the Adinkra symbols": greatness, leadership |
| **Dwennimmen** | "Ram's horns": strength with humility |
| **Nkonsonkonson** | "Chain links": unity, community, working together |
| **Sankofa** | "Go back and get it": learning from the past, legacy |

Rules:

1. Use only these four, from the files in the child theme (`assets/brand/mark-*.webp`). Do not draw, download or generate other symbols, brush marks or "African-style" glyphs, and do not use the old brush-glyph pattern tile.
2. Background patterns are built only from these four marks, and kept subtle.
3. Do not stretch, recolour outside the brand tokens, rotate or combine a symbol with other artwork.
4. In stories, posters, social images and slides made by the team, the same rule applies. If a design seems to need another symbol, ask the brand lead.

## How to use tokens

| Do | Do not |
|---|---|
| Read `var(--c-text)` in a rule | Write `#2f2f2f` in a rule |
| Add a new value to the tokens file first | Add a one-off colour in a stylesheet |
| Use `--s-*` for gaps and padding | Use arbitrary pixel values |

A build check fails if brand colours or fonts appear anywhere outside the tokens file.

## Where the file lives

The parent theme defines the token structure and sensible defaults. The brand values live in the tokens file of the child theme, which overrides the parent. Never edit brand values in the parent.

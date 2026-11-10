# Components

This page describes the reusable pieces of the public site and the functions that build them. Components are plain JavaScript functions that return HTML strings, with styles in `prototype/theme/site.css` and `home.css`.

## Builders

Most live in `prototype/theme/components.js` and hang off the global `W`.

| Function | Builds |
|---|---|
| `W.band(options)` | Title band at the top of an inner page: eyebrow, title, lede, optional tone colour |
| `W.sectionHead(eyebrow, title, more, lede)` | Section heading with an optional "see all" link |
| `W.grid(items, cls)` | Responsive grid of cards |
| `W.card(profile)` | Profile card: poster on top, then industry, name and role |
| `W.articleCard(article, lead)` | Story card, 16:9 image |
| `W.profilePoster(profile)` | The poster (see below) |
| `W.monogram(name, type, size, photo)` | Round or rounded-square initials or photo |
| `W.nomPoster`, `W.nomPortrait`, `W.citation` | Award nominee pieces |
| `W.img(kind, seed, w, h, alt, cls)` | Placeholder image with alt text |
| `W.siteHeader`, `W.siteFooter`, `W.siteCookie` | Page chrome |
| `W.draftNote(text)`, `W.staffBanner(text)` | Notices |

## The poster

A poster is the coloured panel behind a profile or nominee. It has a pattern layer (`poster-pat`), one accent mark (`poster-mark--star`, `--comb`, `--link` or `--spiral`) and, when there is one, a portrait on top. Group colour decides the tone. Organisation marks sit in the top corner so they do not collide with the title.

On profile cards the portrait is a rounded square, like the business tiles, not a small circle. When a profile has no photo the poster shows the coloured panel and mark, which is also what the "On the record" hover card falls back to.

## Cards and lists

Cards come in a grid view (two posters a row on phones, three on wide screens) and a list view with a small poster on the left. The directory toolbar is the shared [filter bar](#filter-bar) with a count, sort and a view switch. Filters write to the URL, so results can be shared (V03).

Awards status shows as a tag pinned to the top-left of a card, with an orange frame on the poster, and as a colour block on the profile page.

## Filter bar

One quiet bar is used on every list page: Events, Stories, the directory lists and Winners. It replaces the boxed form with labelled fields, the collapsed Filters panel, the Apply button and the removable chips.

- **Left:** the filters as borderless inline lists with a thin underline and a small caret, such as "All cities" or "All industries". A filter that is on gets an orange underline. On Winners a search field sits first.
- **Right:** the sort as "Sort: Newest" where the page has one, the view switch on the directory, the number of results, and a **Clear** link that appears only while a filter is on.
- **Behaviour:** choosing a value applies it at once. A visually hidden Apply button keeps the form working without JavaScript. Every list has an accessible name, a visible keyboard focus ring and a result count that is announced.
- **Story types** are a plain row of text tabs with an underline on the current one, not bordered chips.
- Filters write to the address, so a filtered view can be shared.

## Event agenda row and poster

The Events pages use their own look so they are never mistaken for stories (REQ-264). An **agenda row** has an orange date block (day over month, in tabular figures), the title in display type, the place and organiser, a status tag (Upcoming, Online or Past), an optional small image on wide screens and an arrow. A row slides in and turns charcoal on hover. Past rows are muted with a grey date block. A compact row without the image is used on Home. The **poster** on an event page is a full-width orange block, or charcoal when the event is past, with the date at poster size and the title beside it.

## Header and drawer

The header is sticky, translucent with a backdrop blur, and has a mega menu on wide screens. On phones the menu opens a drawer.

| Behaviour | How |
|---|---|
| Transparent over the home hero | `html.is-home.at-top` classes; solid again on hover or focus |
| No flash on refresh | An inline script in `index.html` sets the state before first paint (see [Motion and performance](/theme/motion-and-performance)) |
| Drawer sections animate | `details` elements open and close with a height and fade animation; the chevron turns and items rise in one after another |
| Search | A full-screen panel opened from the header icon; results cover profiles and stories (REQ-254) |
| Nominate link | Hidden from menus and the footer while nominations are closed (REQ-259) |

## Buttons and links

Buttons use `.btn` with variants `alt` (outline) and `sm`. The call to action is orange on charcoal text. Text links use an underline that retracts on hover; arrow icons slide a few pixels.

## Forms

All public forms share one pattern: `W.field(label, name, options)` renders the label, input and hint. Options include `type`, `req`, `hint`, `options` for selects and `value`. Every public form also carries `W.bot()`, an "I am human" checkbox that stands in for the real spam check (rate limits and a honeypot in the WordPress build). Submitting calls a handler in `W.forms` and ends on the `#/done` page with a kind, such as `claim`, `digest` or `contact`.

Forms in the prototype: correction, removal, report, claim, contact, get-listed enquiry, stories newsletter, story comment, nomination steps and the data-rights request.

## Labels and notices

| Piece | Rule |
|---|---|
| Review-state label | "Compiled automatically from public sources. Human review pending." on unreviewed organisation profiles |
| Sponsored | Dashed purple label with square corners, on stories only; never styled like a status |
| Draft note | Small info line marking copy that is still a draft |
| Staff banner | Shown to staff previewing an unpublished page |

## Logo through masks

`W.logoSvg()` and `wordmark()` return spans (`.wiw-w`, `.wiw-logo`) whose shape comes from a CSS mask holding a data URI. Colour follows `currentColor`, so the logo turns light over the dark hero and dark elsewhere without a second file.

## Where to look

Section comments in `site.css` mark each block (buttons, header and mega nav, cards, directory, profile, article, forms, awards, about, stories, nominate, comments). Search for the comment, not the line number.

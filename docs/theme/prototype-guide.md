# Prototype guide

This page explains what the clickable prototype is, how it is built, and how to add or change a page. It complements the older `prototype/README.md`, which still describes some removed features.

## What it is

A static site of plain HTML, CSS and JavaScript in `prototype/`. There is no build step and no server. It shows the real behaviour of the product with sample data. Every name in it is a sample. It is not the product: the real site is WordPress.

## How it is put together

| Layer | Files | Job |
|---|---|---|
| Entry | `index.html` | Loads styles and scripts in order |
| Brand values | `tokens.js`, `theme/tokens.css` | Name, colours, fonts |
| Data | `data.js` | Sample profiles, stories, awards, claims, users |
| Core | `core.js` | State, visibility rules, router, events, chrome |
| Public views | `views-public.js`, `views-awards.js`, `theme/home-view.js` | Visitor pages and forms |
| Link views | `views-links.js` | Single-use link pages (customer intake, consent, expiry) |
| Staff views | `views-staff1.js`, `views-staff2.js` | Staff screens |
| Theme | `theme/*.css`, `components.js`, `motion.js`, `home.js` | High-fidelity look and motion |
| Inventory and flows | `inventory.js`, `flows.js` | The screen list and the scripted walkthroughs |

## Routing

Routes use the URL hash, for example `#/profile/aisha-nwosu-tetteh`. A page registers itself with:

`W.route(pattern, inventoryId, handler, options)`

Patterns can hold parameters such as `/profile/:slug`. The handler returns an HTML string. The router also accepts a query string, so directory filters are shareable links.

## Two looks, one page

Public routes (inventory ids starting with V, N or O) use the theme styles. Staff and tool routes keep the wireframe styles. `core.js` switches the stylesheets through their `media` attribute, so the change is instant. Staff screens get a wireframe bar and a "preview of flows only" banner.

## The tools

| Tool | Where | Use |
|---|---|---|
| Preview as | Top bar | Switch between Visitor, Reviewer, Editor and Administrator. Menus and 403 pages follow |
| Email outbox | Top bar | Every email the system would send; open the links to act as the recipient |
| Inventory, State | Top bar | The screen list; the raw saved state |
| Reset sample data | Top bar | Start over |
| Design review | Top bar, `#/design-review` | Every public page and state in one list, plus an Awards stage switch (soon, open, nominees, winners) |
| Flows | Bottom left | 17 scripted walkthroughs, each linking to inventory entries |
| Design notes | Bottom right | The note for the screen you are on |
| Hide tools | Top bar | Tucks the bar away; on the home page it starts hidden |

There is no staff sign-in screen any more (D-059): WordPress provides the real one, so the prototype uses the role switch.

## Sample data and state

State lives in the browser's `localStorage` under the key `wiw-wireframe-v12`. Nothing leaves the browser. When the data shape changes, the version in `core.js` and `data.js` is bumped so old state is not reused. The seed includes published, pending and unpublished profiles, one pending claim and one claimed profile with an assigned user, stories (one sponsored), nominations and arrangements.

## Add or change a page

1. Write the handler with `W.route(...)` in the right views file. Use the theme builders from [Components](/theme/components).
2. Add an entry to `inventory.js`: id, name, the REQ numbers it serves, decisions, tag (S for specified, I for implied), route and a note.
3. If the page needs a form, add a handler on `W.forms` and a `done` page kind if it has a confirmation.
4. Run `node scripts/smoke-prototype.js`. It fails if a route has no inventory entry, an entry has no route, or the page renders `undefined` for any role.
5. Run `node scripts/build-inventory.js` from `prototype/`. It rewrites `docs/wireframe-inventory.md`. It fails if a REQ has no screen and no explanation.
6. If the change alters a rule, add a decision to Decisions and amend the REQs.

## What is not built on purpose

Real accounts, payments, the real admin, search ranking, and anything the WordPress build will provide natively (posts, users and roles, taxonomy terms, sign-in). See D-059 and the WordPress fit report.

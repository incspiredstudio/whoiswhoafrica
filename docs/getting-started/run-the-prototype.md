# Run the prototype

This page shows how to open the clickable prototype on your computer, how to view it on a phone, and how to move around it by role and by flow.

## Open it

The prototype is static files in `prototype/`. You need no install.

| Way | Steps |
|---|---|
| Double-click | Open `prototype/index.html` in a browser |
| Local server | In `prototype/`, run `python -m http.server 8000` and open `http://localhost:8000` |
| GitHub Pages | A workflow publishes `prototype/` on pushes to `main` and `chore/structured-build-setup`. A repo admin must set Settings, Pages, Source to GitHub Actions once |

Opening the file directly works for everything, including the logo, because the masks are embedded in the CSS.

## View it on a phone

The phone and the computer must be on the same Wi-Fi.

1. Find the computer's local address with `ipconfig` (look for the IPv4 address, for example 192.168.1.4).
2. Serve the folder on all interfaces. Python's server works for most pages: `python -m http.server 8080 --bind 0.0.0.0`.
3. Allow the port through Windows Firewall if the phone cannot connect.
4. On the phone, open `http://<that address>:8080/`.

Two problems come up in practice:

| Problem | Fix |
|---|---|
| The phone shows old pages | Browsers cache scripts and styles. Use a private tab, clear site data, or serve with caching off, for example `npx http-server prototype -c-1 -a 0.0.0.0 -p 8080` |
| Hero videos do not play on an iPhone | iPhones need byte-range requests, which Python's simple server does not support. `http-server` does |

## Roles

The top bar has a **Preview as** menu with four choices.

| Role | Sees |
|---|---|
| Visitor | The public site only |
| Reviewer | The public site and staff screens for review, duplicates, profiles, sources and the suppression list |
| Editor | Everything a Reviewer has, plus enquiries, corrections, requests and reports, claims, nominations and the awards screens |
| Administrator | Everything, including Sponsored stories money fields, customers, import, audit log and Sprint thresholds (roles, grants and exports are WordPress features, not drawn in the prototype) |

Switching role changes the left menu, and a screen you may not open shows a 403 page. In the real site, the Administrator grants or revokes single capabilities in a role-editor plugin and the change shows up at once; the prototype does not draw that screen.

There is no staff sign-in screen in the prototype. WordPress provides that in the real build.

## Flows

The **Flows** button at the bottom left lists 17 scripted walkthroughs. Each one has a start link, the role to use and a list of steps. Examples: nothing is public before approval, unpublish and 410, bulk unpublish and restore, the nominations journey, offline judging, an enquiry becoming a counted subscription, and import with a dry run. Each flow names the inventory entries it touches.

## Useful tools in the top bar

| Control | Does |
|---|---|
| Design review | Opens a list of every public page and state; also has the Awards stage switch |
| Email outbox | Shows each email the system would send. Open the links to act as the recipient |
| Inventory | The list of screens |
| State | The saved state as data |
| Reset sample data | Clears your changes and reseeds |
| Hide tools | Tucks the bar away so you see the page as a visitor would |

**Design notes** at the bottom right explains the page you are on.

## Try these first

1. As a visitor, open the home page, hover the hero videos, then scroll.
2. Open a profile and try Claim this profile, Suggest a correction and Request removal.
3. Switch to Editor, open **Claims** and approve the sample claim. Open the profile and look for the checkmark, which only staff see.
4. As a visitor again, open that profile: the Claim button is gone.
5. Open `#/design-review` and switch the Awards stage to see nominations open, closed, nominees and winners.

## If something looks wrong

Use **Reset sample data**. If the page is blank, check the browser console. Saved state from an older version is replaced automatically when the version key changes.

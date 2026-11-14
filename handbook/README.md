# WHOISWHO Handbook

The product handbook for WHOISWHO: how the site works, how staff run it, and how it is kept healthy after launch. It is a VitePress site and is independent of the `docs/` site.

## Run it

From this folder:

```
npm install
npm run dev        # http://localhost:5174, also reachable on your network
npm run build      # static site in .vitepress/dist
npm run preview    # serve the built site on port 5174
```

## Edit it

- Pages are Markdown files in the folders `start/`, `visitor/`, `staff/`, `concepts/`, `theme/` and `operations/`, plus `index.md` and `glossary.md`.
- The handbook is written for people with no coding experience: task-based, plain language, exact menu names and button labels from wp-admin.
- A line `<!-- verify-after-round-9 -->` at the top of a page marks labels written before the feature was merged; check them against wp-admin and remove the line. Whole-line HTML comments are hidden on the built site.
- The navigation and sidebar are in `.vitepress/config.mts`. Add a new page to the sidebar there.
- Each page opens with a one-paragraph purpose and then numbered steps or short tables.
- `npm run build` fails on a broken link, so run it before sharing a change.

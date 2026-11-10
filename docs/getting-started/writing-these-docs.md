# Writing these docs

This page explains how the docs site is organised, how to run it, and the conventions for adding a page. The site is built with VitePress from the Markdown files in `docs/`.

## Run and build

| Task | Command |
|---|---|
| Install once | `cd docs`, then `npm install` |
| Live preview | `npm run dev`, then open the address it prints (usually `http://localhost:5173`) |
| Production build | `npm run build` |
| Preview the build | `npm run preview` |

The build fails on a page that cannot compile and on a link that goes nowhere, which is the quick way to catch a bad table or a wrong link.

## Where things live

| Path | Holds |
|---|---|
| `docs/index.md`, `docs/overview.md` | The home page and the one-page description of the platform |
| `docs/features/` | What we are building, feature by feature, with acceptance criteria |
| `docs/pages/public/`, `docs/pages/admin/` | Every public page and every admin screen |
| `docs/theme/` | Templates, blocks, brand tokens, components, motion, SEO, acceptance checks |
| `docs/plugins/` | The `whoiswho-core` modules, the guard plugin and third-party plugins |
| `docs/concepts/` | The rules and ideas the product relies on |
| `docs/build/`, `docs/contracts/` | Engineering notes and the import contract |
| `docs/getting-started/` | How to run things and work in the repo |
| `docs/open-questions.md` | Generated from `docs/questions.md` by `scripts/build-open-questions.js`. Do not edit by hand |
| `docs/requirements.md`, `decisions.md`, `questions.md`, `architecture.md`, `adr/`, `spec.md`, `brief/`, `archive/` | Working records. They stay in the repository and are not published in the site |
| `docs/wireframe-inventory.md` | Generated from the prototype. Not published in the site |
| `docs/.vitepress/config.mts` | Site title, menu and sidebar. The sidebar lists each folder's pages automatically |

## Add a page

1. Create the Markdown file in the right folder. Use lower-case, hyphenated names.
2. The sidebar picks it up automatically from its first `#` title. To control the order, add its file name to the folder's list in `docs/.vitepress/config.mts`.
3. Link to it from a related page.
4. Run `npm run build`.

## Conventions

| Convention | Why |
|---|---|
| One `#` title, then one or two sentences saying what the page is for | Readers decide in seconds whether to stay |
| Short sentences, plain words, tables for lists of facts | Easier to scan |
| No raw HTML | The site renders `<tag>` text as text, so it would show up as typed |
| Cite requirement ids as plain text, such as REQ-252, and write the behaviour out. Do not link to the working records | The records are not in the site, and a page must make sense without them |
| Link pages with site paths such as `/theme/components`, not file names | Works with clean URLs |
| Put commands and file names in backticks | They are copied exactly |
| Do not copy a source document into a new page | Explain, summarise and link instead |

Do not use curly double braces in prose; Vue reads them as code.

## Keeping docs true

- When a rule changes, amend the requirement and record the decision in the working records first, then update the pages here.
- Several pages state facts that change: counts, the state key, the number of flows. Check them when you touch the prototype.
- The generated inventory comes from `prototype/inventory.js`. Rebuild it, do not patch it.
- Anything personal or secret stays out. Briefs and notes here are working documents.

## Ownership

The docs are shared documents, so the owner in `STATUS.md` edits them. Other sessions list the changes they need under "For shared docs". See [Working in this repo](/getting-started/working-in-this-repo).

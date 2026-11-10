# Run the checks

This page explains the automated checks for the prototype and what to do when they fail. They need Node 18 or later and no packages.

## The commands

| Command | Where | Does |
|---|---|---|
| `node scripts/smoke-prototype.js` | Repository root | Runs every check on the prototype |
| `node scripts/build-inventory.js` | Repository root or `prototype/` | Rewrites `docs/wireframe-inventory.md` |
| `cd docs && npm run build` | `docs/` | Builds this docs site and fails on a broken page |

## Smoke test

The test loads the prototype scripts into a stubbed browser environment, so it needs no browser. It then:

| Group | Examples of what it asserts |
|---|---|
| Routes | Every inventory entry resolves to a route, and every route has an inventory entry; ids are unique |
| Rendering | Every route renders for every role, is not empty, and shows no `undefined`, `NaN` or `[object` text |
| Scenarios | The same render under changed state: nominations on, all profiles unpublished |
| Rules | A pending profile never appears publicly; money never changes a label; no login or account routes exist; the only flag is Nominations; there is no kill switch text |
| Claims | Approving a claim sets Claimed only; the checkmark is staff only; a claimed profile hides the Claim button |
| Roles | Menus and 403 pages for each role; capability grants and revokes are logged |
| Awards | Stage (Nominated or Winner) and status (Live or Not live) changes, Live nominees and winners on the public pages |
| Public pages | Rules that hold on every public route, such as no staff chrome and no trust badge |
| Audit | The audit log's hash chain is intact |

It prints `N checks, M failures`. The last run was 2,879 checks and 0 failures.

## Inventory build

`build-inventory.js` reads `prototype/inventory.js` and `docs/requirements.md`. It writes the inventory table and a list of requirements that have no screen. Every requirement with no screen needs a written reason (the `NOUI` list in the script). The command exits with an error if one is unexplained.

Do not edit `docs/wireframe-inventory.md` by hand. Change `inventory.js` or the script and rebuild.

## What a failure means

| Message starts with | Likely cause | Fix |
|---|---|---|
| `route resolves` | An inventory route does not match any `W.route` | Fix the route in `inventory.js` or add the page |
| `route has inventory entry` | A new route has no inventory row | Add the row |
| `... has undefined` or `NaN` | A page printed a missing value | Open the page as that role and find the empty field |
| A scenario name such as `approve works` | A rule or handler changed | Read the test line; either the code or the rule changed |
| `403 for ...` | A role gained or lost access | Check the capability table and the menu |
| Inventory: `NOT EXPLAINED` | A new REQ cites no screen | Cite it on a screen, or add a reason in `NOUI` |

When you change a rule on purpose, change the test in the same commit and say why in the message.

## Browser checks

The smoke test does not render real layout. Layout, overflow, contrast and console errors were checked with a Puppeteer script that loads each route at 360, 768, 1280 and 1600 pixels and runs axe-core. That script is not kept in the repository, so recreate it when you need it. See [Responsive and accessibility](/theme/responsive-and-accessibility).

## Checks for the future site

When the WordPress code exists in `site/`, the commands in `CLAUDE.md` apply (`composer test`, `composer lint`, `composer analyse`, `npm run build`, `npx playwright test`, dependency audits). Test names will cite requirement IDs, for example `test_REQ_208_hides_unreviewed_profiles_from_sitemap`. Those scripts are planned and are created in the first foundation step.

## Before you commit

1. Run the smoke test: 0 failures.
2. Rebuild the inventory if you touched `inventory.js` or `requirements.md`.
3. Open the changed pages in a browser at phone and desktop width.
4. Build the docs if you touched them.

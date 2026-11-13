# STATUS

**Shared-docs owner: the website session** (Gate 2 and Gate 3 are done; stages are in `CLAUDE.md`). To hand over, commit and change this line.

**Updated 2026-11-13** after integration rounds 1 to 4 were merged into `chore/structured-build-setup`. Counts come from `gh issue list`; statements about what is built come from reading the code in `site/`, not from a fresh end-to-end run, so they are not claims of verification. The verification step (M5 issues #107, #85, #87) is still open.

**Repo layout:** `docs/` shared (a VitePress site); `site/` the WordPress code; `prototype/` the wireframe; `acquisition/` data acquisition. Interface between acquisition and the website: `docs/contracts/import-format.md`. Other sessions keep their own status in `prototype/STATUS.md` or `acquisition/STATUS.md`.

## Where the project is

**355 issues closed, 43 open** (398 in total) across milestones M0 to M5.

| Milestone | Closed | Open |
|---|---|---|
| M0 Foundations | 76 | 3 (all human: #1, #2, #3) |
| M1 The record | 78 | 3 (all human: #5, #6, #7) |
| M2 Reading and finding | 51 | 2 (human: #8, #9) |
| M3 Trust and revenue | 56 | 9 |
| M4 Recognition | 41 | 3 (human: #18, #20, #26) |
| M5 Launch | 20 | 18 |
| No milestone (discovered during the build) | 33 | 5 |

## What is built (in `site/`)

- **M0 Foundations:** scaffold, PHPCS, PHPStan, PHPUnit on the WordPress test suite, Playwright with axe, asset build, wp-config template, runbook, CI, plugin `whoiswho-core`, must-use plugin `whoiswho-guard`, parent theme `whoiswho` and active child theme `whoiswho-child`, migration framework, capability catalogue (`config/capabilities.php`, version 4) and roles.
- **M1 The record:** profile post type and the `wiw_profile_state` table, the review state machine and queue, duplicates, import path and Sources, suppression, corrections and removals, public profile read model, sitemap and SEO output.
- **M2 Reading and finding:** home and curation, stories, search, newsletter and Subscribers screen.
- **M3 Trust and revenue:** claims and My profiles, enquiries and requests with `WIW-` references, customers and single-use invitations, sponsored stories (`wiw_arrangements`, `wiw_placements`), data-rights intake.
- **M4 Recognition:** award categories, nominations and the Nominations switch, events, comments policy, pages and templates, home animation stage.
- **Staff admin:** the dashboard tiles are widgets on the WordPress Dashboard (the old WHOISWHO Dashboard screen is gone; its URL redirects). The menu is split into top-level groups by job. See `docs/pages/admin/index.md`.
- **Plugins approved by the owner:** Two Factor 0.17.0, User Role Editor 4.67 (Administrator only), WP Super Cache 3.1.5 (off by default until #719 is settled). No Akismet. See `docs/plugins/third-party.md`.
- **Sample content:** seeded and removed with `php bin/wiw.php sample:seed` and `sample:remove`; `content:find-sample` reports what is left; `pages:seed` creates the starter pages. See `docs/build/launch-checklist.md`.

## What remains (43 open issues)

**Human-only (need the owner or an outside party):**
#1 sending mailbox and SPF, DKIM, DMARC; #2 approve plugin choices and the vetting rule (answered by the owner, see `docs/questions.md`; the issue still needs closing); #3, #8, #26, #31 font, video and photograph licences and final copy; #5, #6, #7 response promises, label wording, import contract unknowns; #9 newsletter sending; #10 claim evidence standard; #12 processor register; #13 DPIA and data-protection contact; #14 retention schedule with Legal; #18 awards programme details; #20 comment questions; #37 live host limits; #38 deliverability; #68 enrol two-factor; #69 weekly plugin review; #70 remove sample content from the live site; #85 screen reader and real device pass; #128 the live migration with the owner's approval; #299 acquisition dry run; #544 response period for data requests (a 30-day period is recommended, not yet confirmed).

**Deferred by owner decision:** legal pages (#11, #32), backups and restores (#36, #72). Evidence file uploads are out of launch scope (#638).

**Blocked or waiting:** #719 the page cache stores a stale CSP nonce (the cache stays off until it is settled); #128 waits on the owner's approval and on #71, the staging rehearsal.

**Open but built, to be confirmed:** the integration rounds merge into `chore/structured-build-setup`, which is not the default branch, so `Closes #N` does not close issues. Branches for #260 (export sponsored story deals to CSV), #266 (Your data rights page) and #341 (Profile manager role recipe) have CI runs and were merged through integration rounds, so those issues are probably done and need only a reviewer to close them (the reviewer step marks work done). The open count above may therefore overstate the remaining work.

**Buildable (no human needed):** #343 invitation and consent pages (the invitation route exists in code; confirm the pages); #67 and #73 accessibility and image fixes; #71 staging rehearsal; #40 analytics cookie check; #87 concurrency check; #107 verify each launch checklist item; #690 importable demo content with page templates; #733 My profiles contact and photograph edits.

## How to run it locally

- From `site/`: `composer install` and `npm ci`; copy `.env.example` to the secrets file yourself.
- Main path without Docker: `powershell -File deploy/local/setup.ps1` (WordPress Studio). Optional: `npx wp-env start` with Docker. Mail goes to Mailpit with `deploy/local/mailpit.ps1`. Details in `site/README.md` and `docs/runbook.md`.
- Seed demonstration content: `php bin/wiw.php sample:seed`; remove it with `sample:remove`. Run jobs with `php bin/wiw.php jobs:run`.
- A bare `php -S` router has been used for quick checks in the integration rounds; it is not documented or kept in the repository.

## CI gates (every pull request)

Eleven checks in `.github/workflows/ci.yml`: lint, analyse, test, visibility, build, e2e, audit, plugins, brand, secrets, pr-body. A weekly scan runs audit and secrets (`security-weekly.yml`). One pull request per issue, with `Closes #N` and the REQ list in the body.

## Decisions and questions

See `docs/decisions.md` and `docs/questions.md` (it has a section with the owner answers recorded on 2026-11-13). Earlier history of the planning stage (gates, rulings, wireframe review rounds) is in `docs/decisions.md` and `docs/archive/`.

## Next up

- Owner: close or answer the human issues above, starting with the licences (#8, #31), the host check (#37), the mailbox (#1) and the data-rights period (#544).
- Website session: the buildable issues listed above, then the verification pass (#107).
- Acquisition session: see `acquisition/STATUS.md`; the first real import batch is #299.
